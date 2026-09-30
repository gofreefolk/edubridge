<?php

namespace App\Services\Notice;

use App\Jobs\SendUrgentNoticeWhatsApp;
use App\Models\Notice;
use App\Models\NoticeAttachment;
use App\Models\NoticeAudience;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\Storage\AttachmentStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NoticeService
{
    public function __construct(
        private readonly AttachmentStorageService $attachmentStorage,
    ) {}

    public function create(User $author, array $data): Notice
    {
        return DB::transaction(function () use ($author, $data) {
            $notice = Notice::query()->create([
                'school_id' => $data['school_id'],
                'author_id' => $author->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'title_en' => $data['title_en'] ?? null,
                'body_en' => $data['body_en'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'audience_type' => $data['audience_type'] ?? 'whole_school',
                'pinned_until' => isset($data['pin_days'])
                    ? now()->addDays((int) $data['pin_days'])
                    : null,
                'status' => 'draft',
            ]);

            $this->syncAudiences($notice, $data['audiences'] ?? []);

            if (! empty($data['attachments'])) {
                foreach ($data['attachments'] as $file) {
                    if ($file instanceof UploadedFile) {
                        $this->attachFile($notice, $file);
                    }
                }
            }

            return $notice->fresh(['attachments', 'audiences']);
        });
    }

    public function update(Notice $notice, array $data): Notice
    {
        if ($notice->status === 'archived') {
            throw new InvalidArgumentException('notice_archived');
        }

        return DB::transaction(function () use ($notice, $data) {
            $notice->update([
                'title' => $data['title'] ?? $notice->title,
                'body' => $data['body'] ?? $notice->body,
                // Nullable translations can be cleared by sending null explicitly.
                'title_en' => array_key_exists('title_en', $data) ? $data['title_en'] : $notice->title_en,
                'body_en' => array_key_exists('body_en', $data) ? $data['body_en'] : $notice->body_en,
                'priority' => $data['priority'] ?? $notice->priority,
                'audience_type' => $data['audience_type'] ?? $notice->audience_type,
                'pinned_until' => array_key_exists('pin_days', $data)
                    ? ($data['pin_days'] ? now()->addDays((int) $data['pin_days']) : null)
                    : $notice->pinned_until,
            ]);

            if (isset($data['audiences'])) {
                $this->syncAudiences($notice, $data['audiences']);
            }

            if (array_key_exists('scheduled_publish_at', $data) && $notice->status === 'draft') {
                $scheduledAt = $data['scheduled_publish_at']
                    ? Carbon::parse($data['scheduled_publish_at'])
                    : null;

                if ($scheduledAt === null || $scheduledAt->isFuture()) {
                    $notice->update(['scheduled_publish_at' => $scheduledAt]);
                }
            }

            return $notice->fresh(['attachments', 'audiences']);
        });
    }

    public function publish(Notice $notice, ?Carbon $scheduledAt = null): Notice
    {
        if ($notice->status === 'archived') {
            throw new InvalidArgumentException('notice_archived');
        }

        if ($scheduledAt && $scheduledAt->isFuture()) {
            if ($notice->status === 'published') {
                throw new InvalidArgumentException('notice_already_published');
            }

            $notice->update(['scheduled_publish_at' => $scheduledAt]);

            return $notice->fresh();
        }

        $this->publishImmediately($notice);

        return $notice->fresh();
    }

    public function publishDueScheduled(): int
    {
        $count = 0;

        Notice::query()
            ->where('status', 'draft')
            ->whereNotNull('scheduled_publish_at')
            ->where('scheduled_publish_at', '<=', now())
            ->orderBy('scheduled_publish_at')
            ->each(function (Notice $notice) use (&$count) {
                if ($this->publishImmediately($notice)) {
                    $count++;
                }
            });

        return $count;
    }

    public function unpublish(Notice $notice): Notice
    {
        if ($notice->status !== 'published') {
            throw new InvalidArgumentException('notice_not_published');
        }

        $notice->update([
            'status' => 'archived',
            'scheduled_publish_at' => null,
        ]);

        return $notice->fresh();
    }

    public function sendUrgentWhatsApp(Notice $notice, bool $force = false): int
    {
        if ($notice->status !== 'published' || $notice->priority !== 'urgent') {
            throw new InvalidArgumentException('notice_not_urgent');
        }

        if (! $force && $notice->whatsapp_sent_at) {
            throw new InvalidArgumentException('whatsapp_already_sent');
        }

        SendUrgentNoticeWhatsApp::dispatch($notice->id);

        return $this->eligibleRecipientsQuery($notice)->count();
    }

    /**
     * Full read report, including who has and hasn't read the notice.
     *
     * @return array{eligible_count: int, read_count: int, read_percent: float, readers: array<int, array<string, mixed>>, unread: array<int, array<string, mixed>>}
     */
    public function analytics(Notice $notice): array
    {
        $eligible = $this->eligibleRecipientsQuery($notice)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $reads = $notice->reads()
            ->whereIn('user_id', $eligible->pluck('id'))
            ->pluck('read_at', 'user_id');

        $readers = [];
        $unread = [];

        foreach ($eligible as $user) {
            $readAt = $reads->get($user->id);
            $entry = [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'read_at' => $readAt ? Carbon::parse($readAt)->toIso8601String() : null,
            ];

            if ($readAt) {
                $readers[] = $entry;
            } else {
                $unread[] = $entry;
            }
        }

        return [
            ...$this->percentages(count($eligible), count($readers)),
            'readers' => $readers,
            'unread' => $unread,
        ];
    }

    /**
     * Counts only — two aggregate queries, safe to call per notice in a list.
     *
     * @return array{eligible_count: int, read_count: int, read_percent: float}
     */
    public function analyticsCounts(Notice $notice): array
    {
        $eligibleIds = $this->eligibleRecipientsQuery($notice)->select('users.id');

        return $this->percentages(
            $this->eligibleRecipientsQuery($notice)->count(),
            $notice->reads()->whereIn('user_id', $eligibleIds)->count(),
        );
    }

    /**
     * Parents/grandparents at the school who should receive this notice.
     *
     * @return Collection<int, User>
     */
    public function eligibleRecipientUsers(Notice $notice): Collection
    {
        return $this->eligibleRecipientsQuery($notice)->get();
    }

    /**
     * Single query: active parents at the school with at least one child in the audience.
     */
    public function eligibleRecipientsQuery(Notice $notice): Builder
    {
        $query = User::query()
            ->whereIn('users.id', DB::table('school_user')
                ->select('user_id')
                ->where('school_id', $notice->school_id)
                ->whereIn('role', ['parent', 'grandparent'])
                ->where('is_active', true));

        if ($notice->audience_type === 'smc_only' || ! in_array($notice->audience_type, ['whole_school', 'class', 'section'], true)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('children', function (Builder $children) use ($notice) {
            $children->where('students.school_id', $notice->school_id);

            if ($notice->audience_type === 'class') {
                $children->whereIn('students.school_class_id', $notice->audiences()->whereNotNull('school_class_id')->select('school_class_id'));
            } elseif ($notice->audience_type === 'section') {
                $children->whereIn('students.section_id', $notice->audiences()->whereNotNull('section_id')->select('section_id'));
            }
        });
    }

    /**
     * Moves a draft to published exactly once. The conditional update means two
     * overlapping scheduler runs (or a double click) cannot both publish it and
     * dispatch the WhatsApp alert twice.
     */
    private function publishImmediately(Notice $notice): bool
    {
        $published = Notice::query()
            ->whereKey($notice->id)
            ->where('status', 'draft')
            ->update([
                'status' => 'published',
                'published_at' => now(),
                'scheduled_publish_at' => null,
                'magic_link_token' => $notice->magic_link_token ?? Notice::generateMagicLinkToken(),
            ]);

        if ($published === 0) {
            return false;
        }

        if ($notice->priority === 'urgent') {
            SendUrgentNoticeWhatsApp::dispatch($notice->id);
        }

        return true;
    }

    public function attachFile(Notice $notice, UploadedFile $file): NoticeAttachment
    {
        return $this->attachmentStorage->store($notice, $file);
    }

    /**
     * Published notices the user may see at this school, according to every role
     * they hold there. Each notice carries `is_read` for the user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Notice>
     */
    public function visibleTo(User $user, int $schoolId, ?int $studentId = null): \Illuminate\Database\Eloquent\Collection
    {
        $roles = $user->rolesAtSchool($schoolId);

        $query = Notice::query()
            ->published()
            ->where('school_id', $schoolId)
            ->with(['school:id,name', 'attachments'])
            ->withExists(['reads as is_read' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END")
            ->orderByDesc('pinned_until')
            ->orderByDesc('published_at');

        // A specific child was requested: show only that child's notices.
        if ($studentId !== null) {
            $student = Student::query()->whereKey($studentId)->where('school_id', $schoolId)->first();

            if (! $student || ! ($user->isParentOf($student) || $student->user_id === $user->id)) {
                return $query->whereRaw('1 = 0')->get();
            }

            return $query->where(fn ($q) => $this->applyStudentAudienceFilter($q, $student))->get();
        }

        if ($user->isSuperAdmin() || array_intersect($roles, ['school_admin', 'teacher'])) {
            return $query->get();
        }

        $students = collect();

        if (array_intersect($roles, ['parent', 'grandparent'])) {
            $students = $students->merge($user->children()->where('school_id', $schoolId)->get());
        }

        if (in_array('student', $roles, true)) {
            $students = $students->merge(Student::query()->where('user_id', $user->id)->where('school_id', $schoolId)->get());
        }

        $isSmc = in_array('smc_member', $roles, true);

        if ($students->isEmpty() && ! $isSmc) {
            return $query->whereRaw('1 = 0')->get();
        }

        return $query->where(function ($q) use ($students, $isSmc) {
            foreach ($students as $student) {
                $q->orWhere(fn ($inner) => $this->applyStudentAudienceFilter($inner, $student));
            }

            if ($isSmc) {
                $q->orWhereIn('audience_type', ['whole_school', 'smc_only']);
            }
        })->get();
    }

    public function forSchoolAdmin(int $schoolId)
    {
        return Notice::query()
            ->where('school_id', $schoolId)
            ->with(['school:id,name', 'attachments', 'audiences'])
            ->orderByRaw("CASE WHEN status = 'draft' THEN 0 ELSE 1 END")
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function userCanViewViaMagicLink(User $user, Notice $notice): bool
    {
        if ($notice->status !== 'published' || ! $notice->published_at || ! $notice->magic_link_token) {
            return false;
        }

        // Magic links are the parent-facing WhatsApp entry point; staff use the app.
        return $this->studentsMatchAudience(
            $user->children()->where('school_id', $notice->school_id)->get(),
            $notice,
        );
    }

    public function userCanView(User $user, Notice $notice): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $roles = $user->rolesAtSchool($notice->school_id);

        if (array_intersect($roles, ['school_admin', 'teacher'])) {
            return true;
        }

        if ($notice->status !== 'published') {
            return false;
        }

        if (in_array('smc_member', $roles, true)
            && in_array($notice->audience_type, ['whole_school', 'smc_only'], true)) {
            return true;
        }

        if (array_intersect($roles, ['parent', 'grandparent'])
            && $this->studentsMatchAudience($user->children()->where('school_id', $notice->school_id)->get(), $notice)) {
            return true;
        }

        if (in_array('student', $roles, true)) {
            $own = Student::query()->where('user_id', $user->id)->where('school_id', $notice->school_id)->get();

            return $this->studentsMatchAudience($own, $notice);
        }

        return false;
    }

    private function studentsMatchAudience(Collection $students, Notice $notice): bool
    {
        foreach ($students as $student) {
            $matches = match ($notice->audience_type) {
                'whole_school' => true,
                'class' => $notice->audiences()->where('school_class_id', $student->school_class_id)->exists(),
                'section' => $notice->audiences()->where('section_id', $student->section_id)->exists(),
                default => false,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    private function applyStudentAudienceFilter($query, Student $student): void
    {
        $query->where(function ($q) use ($student) {
            $q->where('audience_type', 'whole_school')
                ->orWhere(function ($q2) use ($student) {
                    $q2->where('audience_type', 'class')
                        ->whereHas('audiences', fn ($a) => $a->where('school_class_id', $student->school_class_id));
                })
                ->orWhere(function ($q2) use ($student) {
                    $q2->where('audience_type', 'section')
                        ->whereHas('audiences', fn ($a) => $a->where('section_id', $student->section_id));
                });
        });
    }

    private function syncAudiences(Notice $notice, array $audiences): void
    {
        foreach ($audiences as $audience) {
            $classId = $audience['school_class_id'] ?? null;
            $sectionId = $audience['section_id'] ?? null;

            if ($classId && ! SchoolClass::query()->whereKey($classId)->where('school_id', $notice->school_id)->exists()) {
                throw new InvalidArgumentException('invalid_reference');
            }

            if ($sectionId && ! Section::query()->whereKey($sectionId)
                ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $notice->school_id))
                ->exists()) {
                throw new InvalidArgumentException('invalid_reference');
            }
        }

        $notice->audiences()->delete();

        foreach ($audiences as $audience) {
            NoticeAudience::query()->create([
                'notice_id' => $notice->id,
                'school_class_id' => $audience['school_class_id'] ?? null,
                'section_id' => $audience['section_id'] ?? null,
            ]);
        }
    }

    public function isPinned(Notice $notice): bool
    {
        return $notice->pinned_until && Carbon::parse($notice->pinned_until)->isFuture();
    }

    /** @return array{eligible_count: int, read_count: int, read_percent: float} */
    private function percentages(int $eligibleCount, int $readCount): array
    {
        return [
            'eligible_count' => $eligibleCount,
            'read_count' => $readCount,
            'read_percent' => $eligibleCount > 0 ? round(($readCount / $eligibleCount) * 100, 1) : 0.0,
        ];
    }
}
