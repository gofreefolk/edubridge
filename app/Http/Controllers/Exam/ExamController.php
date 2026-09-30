<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Student;
use App\Models\User;
use App\Services\Exam\ExamGradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    use AuthorizesSchoolAdmin;

    /** Extra minutes allowed after the deadline for slow networks. */
    private const SUBMIT_GRACE_MINUTES = 2;

    public function __construct(
        private readonly ExamGradingService $gradingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['school_id' => ['required', 'exists:schools,id']]);
        $school = $this->schoolForRoles($request->user(), (int) $data['school_id'], 'teacher', 'school_admin');

        $exams = Exam::query()
            ->where('school_id', $school->id)
            ->with('questions')
            ->latest()
            ->get();

        // Staff see the answer key.
        $exams->each(fn (Exam $exam) => $exam->questions->each->makeVisible('correct_answer'));

        return response()->json(['exams' => $exams]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'school_class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'duration_minutes' => ['integer', 'min:5', 'max:600'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer', 'distinct'],
        ]);

        $school = $this->schoolForRoles($request->user(), (int) $data['school_id'], 'teacher', 'school_admin');
        $this->ensureAcademicRefsBelongToSchool(
            $school->id,
            $data['school_class_id'] ?? null,
            $data['section_id'] ?? null,
            $data['subject_id'] ?? null,
        );

        $ownQuestions = Question::query()
            ->whereIn('id', $data['question_ids'])
            ->whereHas('questionBank', fn ($q) => $q->where('school_id', $school->id))
            ->count();

        if ($ownQuestions !== count($data['question_ids'])) {
            abort(422, __('edubridge.invalid_reference'));
        }

        $exam = DB::transaction(function () use ($data, $school, $request) {
            $exam = Exam::query()->create([
                'school_id' => $school->id,
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

            foreach (array_values($data['question_ids']) as $i => $questionId) {
                $exam->questions()->attach($questionId, ['sort_order' => $i]);
            }

            return $exam;
        });

        $exam->load('questions');
        $exam->questions->each->makeVisible('correct_answer');

        return response()->json(['exam' => $exam], 201);
    }

    public function publish(Request $request, Exam $exam): JsonResponse
    {
        $this->schoolForRoles($request->user(), $exam->school_id, 'teacher', 'school_admin');

        $exam->update(['status' => 'published']);

        return response()->json(['exam' => $exam]);
    }

    public function startAttempt(Request $request, Exam $exam): JsonResponse
    {
        $data = $request->validate(['student_id' => ['required', 'integer']]);
        $student = Student::query()->findOrFail($data['student_id']);

        $this->ensureCanTakeExam($request->user(), $student, $exam);

        if ($exam->status !== 'published' || now()->lt($exam->opens_at) || now()->gt($exam->closes_at)) {
            return response()->json(['message' => __('edubridge.exam_not_open')], 422);
        }

        $attempt = ExamAttempt::query()->firstOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $student->id],
            ['started_at' => now(), 'status' => 'in_progress'],
        );

        if ($attempt->status !== 'in_progress') {
            return response()->json(['message' => __('edubridge.attempt_closed')], 422);
        }

        return response()->json([
            'attempt' => $attempt,
            'deadline' => $this->deadline($attempt, $exam)->toIso8601String(),
            'exam' => $exam->load('questions'), // correct_answer is hidden on Question
        ]);
    }

    public function submitAttempt(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $data = $request->validate(['answers' => ['required', 'array']]);
        $attempt->load(['exam.questions', 'student']);

        $this->ensureCanTakeExam($request->user(), $attempt->student, $attempt->exam);

        if ($attempt->status !== 'in_progress'
            || now()->gt($this->deadline($attempt, $attempt->exam)->addMinutes(self::SUBMIT_GRACE_MINUTES))) {
            return response()->json(['message' => __('edubridge.attempt_closed')], 422);
        }

        // Ignore answers for questions that are not part of this exam.
        $answers = array_intersect_key(
            $data['answers'],
            array_flip($attempt->exam->questions->pluck('id')->all()),
        );

        $attempt = $this->gradingService->submitAttempt($attempt, $answers);

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

        $bank = QuestionBank::query()->findOrFail($data['question_bank_id']);
        $this->schoolForRoles($request->user(), $bank->school_id, 'teacher', 'school_admin');

        $question = Question::query()->create($data);
        $question->makeVisible('correct_answer');

        return response()->json(['question' => $question], 201);
    }

    public function storeQuestionBank(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'subject_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $school = $this->schoolForRoles($request->user(), (int) $data['school_id'], 'teacher', 'school_admin');
        $this->ensureAcademicRefsBelongToSchool($school->id, subjectId: $data['subject_id'] ?? null);

        $bank = QuestionBank::query()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['question_bank' => $bank], 201);
    }

    /**
     * Only the student themselves or a linked parent may take the exam, and only an
     * exam meant for that student's school / class / section.
     */
    private function ensureCanTakeExam(User $user, ?Student $student, Exam $exam): void
    {
        $isOwner = $student && ($student->user_id === $user->id || $user->isParentOf($student));

        $isForStudent = $student
            && $student->school_id === $exam->school_id
            && (! $exam->school_class_id || $exam->school_class_id === $student->school_class_id)
            && (! $exam->section_id || $exam->section_id === $student->section_id);

        if (! $isOwner || ! $isForStudent) {
            abort(403, __('edubridge.forbidden'));
        }
    }

    private function deadline(ExamAttempt $attempt, Exam $exam): Carbon
    {
        $byDuration = $attempt->started_at->copy()->addMinutes((int) $exam->duration_minutes);

        return $byDuration->lt($exam->closes_at) ? $byDuration : $exam->closes_at->copy();
    }
}
