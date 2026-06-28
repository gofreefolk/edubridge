<?php

namespace App\Http\Controllers\Notice;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\NoticeRead;
use App\Services\Notice\NoticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MagicLinkNoticeController extends Controller
{
    public function __construct(
        private readonly NoticeService $noticeService,
    ) {}

    public function show(Request $request, string $token): JsonResponse
    {
        $notice = Notice::query()
            ->published()
            ->where('magic_link_token', $token)
            ->with(['school:id,name', 'attachments'])
            ->first();

        if (! $notice) {
            return response()->json([
                'message' => __('edubridge.notice_not_found'),
            ], 404);
        }

        if (! $this->noticeService->userCanViewViaMagicLink($request->user(), $notice)) {
            return response()->json([
                'message' => __('edubridge.notice_forbidden'),
            ], 403);
        }

        return response()->json([
            'notice' => [
                'id' => $notice->id,
                'title' => $notice->title,
                'body' => $notice->body,
                'title_en' => $notice->title_en,
                'body_en' => $notice->body_en,
                'priority' => $notice->priority,
                'published_at' => $notice->published_at?->toIso8601String(),
                'school' => [
                    'id' => $notice->school->id,
                    'name' => $notice->school->name,
                ],
                'attachments' => $notice->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'filename' => $a->filename,
                    'url' => asset('storage/'.$a->path),
                ]),
            ],
        ]);
    }

    public function markRead(Request $request, string $token): JsonResponse
    {
        $notice = Notice::query()
            ->published()
            ->where('magic_link_token', $token)
            ->first();

        if (! $notice) {
            return response()->json([
                'message' => __('edubridge.notice_not_found'),
            ], 404);
        }

        if (! $this->noticeService->userCanViewViaMagicLink($request->user(), $notice)) {
            return response()->json([
                'message' => __('edubridge.notice_forbidden'),
            ], 403);
        }

        NoticeRead::query()->updateOrCreate(
            [
                'notice_id' => $notice->id,
                'user_id' => $request->user()->id,
            ],
            ['read_at' => now()],
        );

        return response()->json(['read' => true]);
    }
}
