<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Exam;
use App\Models\Homework;
use App\Models\Student;
use App\Models\TimetableSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentPortalController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $request->validate(['student_id' => ['required', 'exists:students,id']]);
        $student = Student::query()->with(['schoolClass', 'section'])->findOrFail($request->query('student_id'));

        $homework = Homework::query()
            ->where('school_class_id', $student->school_class_id)
            ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $student->section_id))
            ->where('due_date', '>=', now()->toDateString())
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $attendance = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->orderByDesc('date')
            ->limit(30)
            ->get();

        $timetable = TimetableSlot::query()
            ->where('school_class_id', $student->school_class_id)
            ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $student->section_id))
            ->with(['subject:id,name', 'teacher:id,name'])
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get();

        $exams = Exam::query()
            ->where('school_class_id', $student->school_class_id)
            ->where('status', 'published')
            ->where('closes_at', '>=', now())
            ->get();

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'class' => $student->schoolClass?->name,
                'section' => $student->section?->name,
            ],
            'homework' => $homework,
            'attendance' => $attendance,
            'timetable' => $timetable,
            'upcoming_exams' => $exams,
        ]);
    }
}
