<?php

namespace App\Services\Exam;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Question;

class ExamGradingService
{
    public function autoGrade(ExamAttempt $attempt): ExamAttempt
    {
        $attempt->load(['answers.question', 'exam.questions']);

        $score = 0;
        $maxScore = 0;

        foreach ($attempt->exam->questions as $question) {
            $maxScore += (float) $question->marks;
            $answer = $attempt->answers->firstWhere('question_id', $question->id);

            if (! $answer) {
                continue;
            }

            $result = $this->gradeAnswer($question, $answer->answer);
            $answer->update([
                'is_correct' => $result['is_correct'],
                'marks_awarded' => $result['marks'],
            ]);
            $score += $result['marks'];
        }

        $attempt->update([
            'score' => $score,
            'max_score' => $maxScore,
            'status' => 'graded',
            'submitted_at' => $attempt->submitted_at ?? now(),
        ]);

        return $attempt->fresh();
    }

    public function gradeAnswer(Question $question, ?string $answer): array
    {
        if ($answer === null || $answer === '') {
            return ['is_correct' => false, 'marks' => 0];
        }

        if (in_array($question->type, ['mcq', 'true_false'], true)) {
            $isCorrect = strtolower(trim($answer)) === strtolower(trim($question->correct_answer ?? ''));

            return [
                'is_correct' => $isCorrect,
                'marks' => $isCorrect ? (float) $question->marks : 0,
            ];
        }

        return ['is_correct' => null, 'marks' => null];
    }

    public function submitAttempt(ExamAttempt $attempt, array $answers): ExamAttempt
    {
        foreach ($answers as $questionId => $value) {
            ExamAnswer::query()->updateOrCreate(
                [
                    'exam_attempt_id' => $attempt->id,
                    'question_id' => $questionId,
                ],
                ['answer' => is_string($value) ? $value : json_encode($value)],
            );
        }

        $attempt->update([
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        return $this->autoGrade($attempt->fresh(['answers', 'exam.questions']));
    }
}
