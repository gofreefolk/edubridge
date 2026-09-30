<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Jobs\SendAbsenceAlert;
use App\Models\AttendanceRecord;
use App\Models\Homework;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TeacherWorkspaceController extends Controller
{
    use AuthorizesSchoolAdmin;

    private const STAFF_ROLES = ['teacher', 'school_admin'];

    public function classRoster(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer'],
        ]);

        $class = SchoolClass::query()->findOrFail($data['school_class_id']);
        $this->schoolForRoles($request->user(), $class->school_id, ...self::STAFF_ROLES);
        $this->ensureAcademicRefsBelongToSchool($class->school_id, $class->id, $data['section_id'] ?? null);

        $query = Student::query()
            ->where('school_id', $class->school_id)
            ->where('school_class_id', $class->id)
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
            'date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array'],
            'records.*.student_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', 'in:present,absent,late,excused'],
        ]);

        $school = $this->schoolForRoles($request->user(), (int) $data['school_id'], ...self::STAFF_ROLES);
        $this->ensureStudentsInSchool($school->id, array_column($data['records'], 'student_id'));

        $date = Carbon::parse($data['date'])->toDateString();

        $absentIds = DB::transaction(function () use ($data, $date, $school, $request) {
            $absentIds = [];

            foreach ($data['records'] as $record) {
                // whereDate, not a plain equality: SQLite stores the cast date with a time part.
                $saved = AttendanceRecord::query()
                    ->where('student_id', $record['student_id'])
                    ->whereDate('date', $date)
                    ->first()
                    ?? new AttendanceRecord(['student_id' => $record['student_id'], 'date' => $date]);

                $saved->fill([
                    'school_id' => $school->id,
                    'marked_by' => $request->user()->id,
                    'status' => $record['status'],
                ])->save();

                if ($saved->status === 'absent' && ! $saved->absence_alert_sent_at) {
                    $absentIds[] = $saved->id;
                }
            }

            return $absentIds;
        });

        // Only alert for today's register; back-filled days would be confusing.
        if ($date === today()->toDateString()) {
            foreach ($absentIds as $id) {
                SendAbsenceAlert::dispatch($id)->afterCommit();
            }
        }

        return response()->json(['saved' => true, 'absence_alerts_queued' => $date === today()->toDateString() ? count($absentIds) : 0]);
    }

    public function assignHomework(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'school_class_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
        ]);

        $school = $this->schoolForRoles($request->user(), (int) $data['school_id'], ...self::STAFF_ROLES);
        $this->ensureAcademicRefsBelongToSchool(
            $school->id,
            $data['school_class_id'],
            $data['section_id'] ?? null,
            $data['subject_id'] ?? null,
        );

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
            'student_id' => ['required', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'term' => ['required', 'string', 'max:50'],
            'score' => ['nullable', 'numeric', 'min:0'],
            'max_score' => ['nullable', 'numeric', 'gt:0'],
            'grade' => ['nullable', 'string', 'max:10'],
        ]);

        $school = $this->schoolForRoles($request->user(), (int) $data['school_id'], ...self::STAFF_ROLES);
        $this->ensureStudentsInSchool($school->id, [$data['student_id']]);
        $this->ensureAcademicRefsBelongToSchool($school->id, subjectId: $data['subject_id'] ?? null);

        $mark = Mark::query()->updateOrCreate(
            [
                'student_id' => $data['student_id'],
                'subject_id' => $data['subject_id'] ?? null,
                'term' => $data['term'],
            ],
            [
                'school_id' => $school->id,
                'score' => $data['score'] ?? null,
                'max_score' => $data['max_score'] ?? 100,
                'grade' => $data['grade'] ?? null,
                'entered_by' => $request->user()->id,
            ],
        );

        return response()->json(['mark' => $mark]);
    }

    /** @param  list<int>  $studentIds */
    private function ensureStudentsInSchool(int $schoolId, array $studentIds): void
    {
        $studentIds = array_unique(array_map('intval', $studentIds));
        $found = Student::query()->whereIn('id', $studentIds)->where('school_id', $schoolId)->count();

        if ($found !== count($studentIds)) {
            abort(422, __('edubridge.invalid_reference'));
        }
    }
}
