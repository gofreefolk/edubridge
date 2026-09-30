<?php

namespace App\Http\Controllers\Notice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notice\StoreNoticeRequest;
use App\Http\Requests\Notice\UpdateNoticeRequest;
use App\Models\Notice;
use App\Services\Notice\NoticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class NoticeController extends Controller
{
    public function __construct(
        private readonly NoticeService $noticeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'student_id' => ['nullable', 'integer'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $studentId = isset($validated['student_id']) ? (int) $validated['student_id'] : null;
        $user = $request->user();

        if (! $user->isSuperAdmin() && $user->rolesAtSchool($schoolId) === []) {
            return response()->json(['message' => __('edubridge.unauthorized_role')], 403);
        }

        $isAdmin = $user->hasRoleAtSchool($schoolId, 'school_admin');

        $notices = $isAdmin
            ? $this->noticeService->forSchoolAdmin($schoolId)
            : $this->noticeService->visibleTo($user, $schoolId, $studentId);

        return response()->json([
            'notices' => $notices->map(fn (Notice $n) => $this->payload($n, includeStats: $isAdmin && $n->status === 'published')),
        ]);
    }

    public function store(StoreNoticeRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('attachments')) {
            $data['attachments'] = $request->file('attachments');
        }

        try {
            $notice = $this->noticeService->create($request->user(), $data);
        } catch (InvalidArgumentException $e) {
            return $this->noticeError($e);
        }

        return response()->json(['notice' => $this->payload($notice)], 201);
    }

    public function show(Request $request, Notice $notice): JsonResponse
    {
        if (! $this->noticeService->userCanView($request->user(), $notice)) {
            return response()->json([
                'message' => __('edubridge.notice_forbidden'),
            ], 403);
        }

        $notice->load(['attachments', 'school:id,name', 'author:id,name', 'audiences']);

        return response()->json(['notice' => $this->payload($notice, detailed: true)]);
    }

    public function update(UpdateNoticeRequest $request, Notice $notice): JsonResponse
    {
        try {
            $notice = $this->noticeService->update($notice, $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->noticeError($e);
        }

        return response()->json(['notice' => $this->payload($notice)]);
    }

    public function publish(Request $request, Notice $notice): JsonResponse
    {
        $this->authorizeNoticeAdmin($request, $notice);

        $data = $request->validate([
            'scheduled_publish_at' => ['nullable', 'date', 'after:now'],
        ]);

        $scheduledAt = isset($data['scheduled_publish_at'])
            ? Carbon::parse($data['scheduled_publish_at'])
            : null;

        try {
            $notice = $this->noticeService->publish($notice, $scheduledAt);
        } catch (InvalidArgumentException $e) {
            return $this->noticeError($e);
        }

        return response()->json(['notice' => $this->payload($notice)]);
    }

    public function unpublish(Request $request, Notice $notice): JsonResponse
    {
        $this->authorizeNoticeAdmin($request, $notice);

        try {
            $notice = $this->noticeService->unpublish($notice);
        } catch (InvalidArgumentException $e) {
            return $this->noticeError($e);
        }

        return response()->json(['notice' => $this->payload($notice)]);
    }

    public function stats(Request $request, Notice $notice): JsonResponse
    {
        $this->authorizeNoticeAdmin($request, $notice);

        return response()->json([
            'stats' => $this->noticeService->analytics($notice),
        ]);
    }

    public function sendWhatsApp(Request $request, Notice $notice): JsonResponse
    {
        $this->authorizeNoticeAdmin($request, $notice);

        $data = $request->validate([
            'force' => ['sometimes', 'boolean'],
        ]);

        try {
            $recipientCount = $this->noticeService->sendUrgentWhatsApp($notice, (bool) ($data['force'] ?? false));
        } catch (InvalidArgumentException $e) {
            return $this->noticeError($e);
        }

        return response()->json([
            'message' => __('edubridge.whatsapp_queued', ['count' => $recipientCount]),
            'recipient_count' => $recipientCount,
        ]);
    }

    private function authorizeNoticeAdmin(Request $request, Notice $notice): void
    {
        abort_unless(
            $request->user()?->hasRoleAtSchool($notice->school_id, 'school_admin'),
            403,
            __('edubridge.unauthorized_role'),
        );
    }

    private function noticeError(InvalidArgumentException $e): JsonResponse
    {
        $key = match ($e->getMessage()) {
            'notice_archived' => 'edubridge.notice_archived',
            'notice_already_published' => 'edubridge.notice_already_published',
            'invalid_reference' => 'edubridge.invalid_reference',
            'notice_not_published' => 'edubridge.notice_not_published',
            'notice_not_urgent' => 'edubridge.notice_not_urgent',
            'whatsapp_already_sent' => 'edubridge.whatsapp_already_sent',
            default => 'edubridge.generic_error',
        };

        return response()->json(['message' => __($key)], 422);
    }

    private function payload(Notice $notice, bool $detailed = false, bool $includeStats = false): array
    {
        $data = [
            'id' => $notice->id,
            'title' => $notice->title,
            'body' => $detailed ? $notice->body : null,
            'title_en' => $notice->title_en,
            'body_en' => $detailed ? $notice->body_en : null,
            'priority' => $notice->priority,
            'audience_type' => $notice->audience_type,
            'status' => $notice->status,
            'published_at' => $notice->published_at?->toIso8601String(),
            'scheduled_publish_at' => $notice->scheduled_publish_at?->toIso8601String(),
            'whatsapp_sent_at' => $notice->whatsapp_sent_at?->toIso8601String(),
            'magic_link_token' => $notice->magic_link_token,
            'is_pinned' => $this->noticeService->isPinned($notice),
            'audiences' => $notice->relationLoaded('audiences')
                ? $notice->audiences->map(fn ($a) => [
                    'school_class_id' => $a->school_class_id,
                    'section_id' => $a->section_id,
                ])
                : [],
            'school' => $notice->relationLoaded('school') ? [
                'id' => $notice->school->id,
                'name' => $notice->school->name,
            ] : null,
            'attachments' => $notice->relationLoaded('attachments')
                ? $notice->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'filename' => $a->filename,
                    'url' => $a->url(),
                ])
                : [],
        ];

        if ($includeStats) {
            $stats = $this->noticeService->analyticsCounts($notice);
            $data['read_count'] = $stats['read_count'];
            $data['eligible_count'] = $stats['eligible_count'];
            $data['read_percent'] = $stats['read_percent'];
        }

        return $data;
    }
}
