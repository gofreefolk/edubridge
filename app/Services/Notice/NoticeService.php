<?php

namespace App\Services\Notice;

use App\Jobs\SendUrgentNoticeWhatsApp;
use App\Models\Notice;
use App\Models\NoticeAudience;
use App\Models\NoticeAttachment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class NoticeService
{
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
        $notice->update([
            'title' => $data['title'] ?? $notice->title,
            'body' => $data['body'] ?? $notice->body,
            'title_en' => $data['title_en'] ?? $notice->title_en,
            'body_en' => $data['body_en'] ?? $notice->body_en,
            'priority' => $data['priority'] ?? $notice->priority,
            'audience_type' => $data['audience_type'] ?? $notice->audience_type,
            'pinned_until' => array_key_exists('pin_days', $data)
                ? ($data['pin_days'] ? now()->addDays((int) $data['pin_days']) : null)
                : $notice->pinned_until,
        ]);

        if (isset($data['audiences'])) {
            $this->syncAudiences($notice, $data['audiences']);
        }

        return $notice->fresh(['attachments', 'audiences']);
    }

    public function publish(Notice $notice): Notice
    {
        $notice->update([
            'status' => 'published',
            'published_at' => now(),
            'magic_link_token' => $notice->magic_link_token ?? Notice::generateMagicLinkToken(),
        ]);

        if ($notice->priority === 'urgent') {
            SendUrgentNoticeWhatsApp::dispatch($notice->id);
        }

        return $notice->fresh();
    }

    public function attachFile(Notice $notice, UploadedFile $file): NoticeAttachment
    {
        $path = $file->store("notices/{$notice->school_id}/{$notice->id}", 'public');

        return NoticeAttachment::query()->create([
            'notice_id' => $notice->id,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function forParent(User $user, int $schoolId, ?int $studentId = null)
    {
        $query = Notice::query()
            ->published()
            ->where('school_id', $schoolId)
            ->with(['school:id,name', 'attachments'])
            ->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END")
            ->orderByDesc('pinned_until')
            ->orderByDesc('published_at');

        if ($studentId) {
            $student = $user->children()->where('students.id', $studentId)->first();
            if ($student) {
                $query->where(fn ($q) => $this->applyStudentAudienceFilter($q, $student));
            }
        } else {
            $children = $user->children()->where('school_id', $schoolId)->get();
            if ($children->isNotEmpty()) {
                $query->where(function ($q) use ($children) {
                    foreach ($children as $child) {
                        $q->orWhere(fn ($inner) => $this->applyStudentAudienceFilter($inner, $child));
                    }
                });
            }
        }

        return $query->get();
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

        return $this->parentMatchesAudience($user, $notice);
    }

    public function userCanView(User $user, Notice $notice): bool
    {
        if ($user->hasAnyRole('super_admin')) {
            return true;
        }

        $role = $user->roleAtSchool($notice->school_id);

        if (in_array($role, ['school_admin', 'teacher'], true)) {
            return true;
        }

        if ($notice->status !== 'published') {
            return false;
        }

        if ($role === 'smc_member') {
            return in_array($notice->audience_type, ['whole_school', 'smc_only'], true);
        }

        if (in_array($role, ['parent', 'grandparent'], true)) {
            return $this->parentMatchesAudience($user, $notice);
        }

        return false;
    }

    private function parentMatchesAudience(User $user, Notice $notice): bool
    {
        $children = $user->children()->where('school_id', $notice->school_id)->get();

        if ($children->isEmpty()) {
            return false;
        }

        foreach ($children as $student) {
            if ($this->studentMatchesAudience($student, $notice)) {
                return true;
            }
        }

        return false;
    }

    private function studentMatchesAudience(Student $student, Notice $notice): bool
    {
        return match ($notice->audience_type) {
            'whole_school' => true,
            'class' => $notice->audiences()->where('school_class_id', $student->school_class_id)->exists(),
            'section' => $notice->audiences()->where('section_id', $student->section_id)->exists(),
            default => false,
        };
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
}
