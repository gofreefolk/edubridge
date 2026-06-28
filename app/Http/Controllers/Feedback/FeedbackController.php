<?php

namespace App\Http\Controllers\Feedback;

use App\Http\Controllers\Controller;
use App\Models\FeedbackMessage;
use App\Models\FeedbackThread;
use App\Models\Student;
use App\Services\Feedback\FeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function __construct(
        private readonly FeedbackService $feedbackService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
        ]);

        $threads = $this->feedbackService
            ->inboxQuery($request->user(), (int) $validated['school_id'])
            ->get()
            ->map(fn (FeedbackThread $thread) => $this->feedbackService->threadPayload($thread));

        return response()->json(['threads' => $threads]);
    }

    public function show(Request $request, FeedbackThread $thread): JsonResponse
    {
        if (! $this->feedbackService->canAccess($request->user(), $thread)) {
            return response()->json(['message' => __('edubridge.forbidden')], 403);
        }

        $thread->load([
            'creator:id,name,phone',
            'student:id,name',
            'messages' => fn ($q) => $q->with('author:id,name')->oldest(),
        ]);

        return response()->json([
            'thread' => $this->feedbackService->threadPayload($thread),
            'can_resolve' => $this->feedbackService->canResolve($request->user(), $thread),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'category' => ['required', 'string', 'in:academic,transport,fees,general'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'direction' => ['required', 'string', 'in:parent_to_teacher,parent_to_admin'],
        ]);

        if ($validated['student_id']) {
            $student = Student::query()->findOrFail($validated['student_id']);
            if ($student->school_id !== (int) $validated['school_id']) {
                return response()->json(['message' => __('edubridge.forbidden')], 403);
            }
        }

        $thread = FeedbackThread::query()->create([
            'school_id' => $validated['school_id'],
            'student_id' => $validated['student_id'] ?? null,
            'created_by' => $request->user()->id,
            'category' => $validated['category'],
            'subject' => $validated['subject'],
            'direction' => $validated['direction'],
            'status' => 'open',
        ]);

        FeedbackMessage::query()->create([
            'feedback_thread_id' => $thread->id,
            'author_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        $thread->load([
            'creator:id,name,phone',
            'student:id,name',
            'messages' => fn ($q) => $q->with('author:id,name')->oldest(),
        ]);

        return response()->json([
            'thread' => $this->feedbackService->threadPayload($thread),
        ], 201);
    }

    public function reply(Request $request, FeedbackThread $thread): JsonResponse
    {
        if (! $this->feedbackService->canAccess($request->user(), $thread)) {
            return response()->json(['message' => __('edubridge.forbidden')], 403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        FeedbackMessage::query()->create([
            'feedback_thread_id' => $thread->id,
            'author_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        if ($thread->status === 'open') {
            $thread->update(['status' => 'acknowledged']);
        }

        $thread->load([
            'creator:id,name,phone',
            'student:id,name',
            'messages' => fn ($q) => $q->with('author:id,name')->oldest(),
        ]);

        return response()->json([
            'thread' => $this->feedbackService->threadPayload($thread),
        ]);
    }

    public function resolve(Request $request, FeedbackThread $thread): JsonResponse
    {
        if (! $this->feedbackService->canResolve($request->user(), $thread)) {
            return response()->json(['message' => __('edubridge.forbidden')], 403);
        }

        $thread->update(['status' => 'resolved']);

        return response()->json(['thread' => ['id' => $thread->id, 'status' => 'resolved']]);
    }
}
