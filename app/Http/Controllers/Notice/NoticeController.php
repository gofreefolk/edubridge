<?php

namespace App\Http\Controllers\Notice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notice\StoreNoticeRequest;
use App\Http\Requests\Notice\UpdateNoticeRequest;
use App\Models\Notice;
use App\Services\Notice\NoticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function __construct(
        private readonly NoticeService $noticeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $schoolId = (int) $request->query('school_id');
        $studentId = $request->query('student_id') ? (int) $request->query('student_id') : null;
        $user = $request->user();

        $notices = $user->hasAnyRole('school_admin', 'super_admin')
            ? $this->noticeService->forSchoolAdmin($schoolId)
            : $this->noticeService->forParent($user, $schoolId, $studentId);

        return response()->json([
            'notices' => $notices->map(fn (Notice $n) => $this->payload($n)),
        ]);
    }

    public function store(StoreNoticeRequest $request): JsonResponse
    {
        $notice = $this->noticeService->create($request->user(), $request->validated());

        return response()->json(['notice' => $this->payload($notice)], 201);
    }

    public function show(Request $request, Notice $notice): JsonResponse
    {
        if (! $this->noticeService->userCanView($request->user(), $notice)) {
            return response()->json([
                'message' => __('edubridge.notice_forbidden'),
            ], 403);
        }

        $notice->load(['attachments', 'school:id,name', 'author:id,name']);

        return response()->json(['notice' => $this->payload($notice, detailed: true)]);
    }

    public function update(UpdateNoticeRequest $request, Notice $notice): JsonResponse
    {
        $notice = $this->noticeService->update($notice, $request->validated());

        return response()->json(['notice' => $this->payload($notice)]);
    }

    public function publish(Request $request, Notice $notice): JsonResponse
    {
        $notice = $this->noticeService->publish($notice);

        return response()->json(['notice' => $this->payload($notice)]);
    }

    private function payload(Notice $notice, bool $detailed = false): array
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
            'magic_link_token' => $notice->magic_link_token,
            'is_pinned' => $this->noticeService->isPinned($notice),
            'school' => $notice->relationLoaded('school') ? [
                'id' => $notice->school->id,
                'name' => $notice->school->name,
            ] : null,
            'attachments' => $notice->relationLoaded('attachments')
                ? $notice->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'filename' => $a->filename,
                    'url' => asset('storage/'.$a->path),
                ])
                : [],
        ];

        return $data;
    }
}
