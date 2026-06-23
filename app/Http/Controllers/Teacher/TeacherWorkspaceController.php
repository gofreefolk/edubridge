<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Homework;
use App\Models\Mark;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherWorkspaceController extends Controller
{
    public function classRoster(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
        ]);

        $query = Student::query()
            ->where('school_class_id', $data['school_class_id'])
            ->where('status', 'active');

        if (! empty($data['section_id'])) {
            $query->where('section_id', $data['section_id']);
        }

        return response()->json(['students' => $query->orderBy('name')->get()]);
    }

    public function markAttendance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'date' => ['required', 'date'],
            'records' => ['required', 'array'],
            'records.*.student_id' => ['required', 'exists:students,id'],
            'records.*.status' => ['required', 'in:present,absent,late,excused'],
        ]);

        foreach ($data['records'] as $record) {
            AttendanceRecord::query()->updateOrCreate(
                ['student_id' => $record['student_id'], 'date' => $data['date']],
                [
                    'school_id' => $data['school_id'],
                    'marked_by' => $request->user()->id,
                    'status' => $record['status'],
                ],
            );
        }

        return response()->json(['saved' => true]);
    }

    public function assignHomework(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
        ]);

        $homework = Homework::query()->create([
            ...$data,
            'teacher_id' => $request->user()->id,
        ]);

        return response()->json(['homework' => $homework], 201);
    }

    public function enterMarks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'student_id' => ['required', 'exists:students,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'term' => ['required', 'string'],
            'score' => ['nullable', 'numeric'],
            'max_score' => ['nullable', 'numeric'],
            'grade' => ['nullable', 'string'],
        ]);

        $mark = Mark::query()->updateOrCreate(
            [
                'student_id' => $data['student_id'],
                'subject_id' => $data['subject_id'] ?? null,
                'term' => $data['term'],
            ],
            [
                'school_id' => $data['school_id'],
                'score' => $data['score'] ?? null,
                'max_score' => $data['max_score'] ?? 100,
                'grade' => $data['grade'] ?? null,
                'entered_by' => $request->user()->id,
            ],
        );

        return response()->json(['mark' => $mark]);
    }
}
