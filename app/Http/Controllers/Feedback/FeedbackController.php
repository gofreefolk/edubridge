<?php

namespace App\Http\Controllers\Feedback;

use App\Http\Controllers\Controller;
use App\Models\FeedbackMessage;
use App\Models\FeedbackThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['school_id' => ['required', 'exists:schools,id']]);

        $threads = FeedbackThread::query()
            ->where('school_id', $request->query('school_id'))
            ->where(function ($q) use ($request) {
                $q->where('created_by', $request->user()->id)
                    ->orWhere('assigned_to', $request->user()->id);
            })
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->latest()
            ->get();

        return response()->json(['threads' => $threads]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'student_id' => ['nullable', 'exists:students,id'],
            'category' => ['required', 'string'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'direction' => ['required', 'in:parent_to_teacher,parent_to_smc,teacher_to_parent'],
        ]);

        $thread = FeedbackThread::query()->create([
            'school_id' => $data['school_id'],
            'student_id' => $data['student_id'] ?? null,
            'created_by' => $request->user()->id,
            'category' => $data['category'],
            'subject' => $data['subject'],
            'direction' => $data['direction'],
            'status' => 'open',
        ]);

        FeedbackMessage::query()->create([
            'feedback_thread_id' => $thread->id,
            'author_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return response()->json(['thread' => $thread->load('messages')], 201);
    }

    public function reply(Request $request, FeedbackThread $thread): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string']]);

        $message = FeedbackMessage::query()->create([
            'feedback_thread_id' => $thread->id,
            'author_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        if ($thread->status === 'open' && $thread->created_by !== $request->user()->id) {
            $thread->update(['status' => 'acknowledged']);
        }

        return response()->json(['message' => $message]);
    }

    public function resolve(FeedbackThread $thread): JsonResponse
    {
        $thread->update(['status' => 'resolved']);

        return response()->json(['thread' => $thread]);
    }
}
