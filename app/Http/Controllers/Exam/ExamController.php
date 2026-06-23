<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Services\Exam\ExamGradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function __construct(
        private readonly ExamGradingService $gradingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['school_id' => ['required', 'exists:schools,id']]);

        $exams = Exam::query()
            ->where('school_id', $request->query('school_id'))
            ->with('questions')
            ->latest()
            ->get();

        return response()->json(['exams' => $exams]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'title' => ['required', 'string'],
            'instructions' => ['nullable', 'string'],
            'duration_minutes' => ['integer', 'min:5'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'question_ids' => ['required', 'array'],
            'question_ids.*' => ['exists:questions,id'],
        ]);

        $exam = Exam::query()->create([
            'school_id' => $data['school_id'],
            'school_class_id' => $data['school_class_id'] ?? null,
            'section_id' => $data['section_id'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'opens_at' => $data['opens_at'],
            'closes_at' => $data['closes_at'],
            'status' => 'draft',
        ]);

        foreach ($data['question_ids'] as $i => $questionId) {
            $exam->questions()->attach($questionId, ['sort_order' => $i]);
        }

        return response()->json(['exam' => $exam->load('questions')], 201);
    }

    public function publish(Exam $exam): JsonResponse
    {
        $exam->update(['status' => 'published']);

        return response()->json(['exam' => $exam]);
    }

    public function startAttempt(Request $request, Exam $exam): JsonResponse
    {
        $data = $request->validate(['student_id' => ['required', 'exists:students,id']]);

        $attempt = ExamAttempt::query()->firstOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $data['student_id']],
            ['started_at' => now(), 'status' => 'in_progress'],
        );

        return response()->json([
            'attempt' => $attempt,
            'exam' => $exam->load('questions'),
        ]);
    }

    public function submitAttempt(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $data = $request->validate(['answers' => ['required', 'array']]);

        $attempt = $this->gradingService->submitAttempt($attempt, $data['answers']);

        return response()->json(['attempt' => $attempt->load('answers')]);
    }

    public function storeQuestion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question_bank_id' => ['required', 'exists:question_banks,id'],
            'type' => ['required', 'in:mcq,true_false,short_answer'],
            'body' => ['required', 'string'],
            'options' => ['nullable', 'array'],
            'correct_answer' => ['nullable', 'string'],
            'marks' => ['numeric', 'min:0'],
        ]);

        $question = Question::query()->create($data);

        return response()->json(['question' => $question], 201);
    }

    public function storeQuestionBank(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'name' => ['required', 'string'],
        ]);

        $bank = QuestionBank::query()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['question_bank' => $bank], 201);
    }
}
