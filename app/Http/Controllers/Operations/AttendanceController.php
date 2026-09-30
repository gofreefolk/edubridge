<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    use AuthorizesSchoolAdmin;

    private const STAFF_ROLES = ['teacher', 'school_admin'];

    private const STATUSES = ['present', 'absent', 'late', 'excused'];

    /**
     * Class roster with each student's status on one date, for the marking screen.
     */
    public function sheet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $class = SchoolClass::query()->findOrFail($data['school_class_id']);
        $this->schoolForRoles($request->user(), $class->school_id, ...self::STAFF_ROLES);
        $this->ensureAcademicRefsBelongToSchool($class->school_id, $class->id, $data['section_id'] ?? null);

        $date = Carbon::parse($data['date'] ?? today())->toDateString();
        $students = $this->roster($class, $data['section_id'] ?? null);

        $records = AttendanceRecord::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->whereDate('date', $date)
            ->get()
            ->keyBy('student_id');

        return response()->json([
            'school_id' => $class->school_id,
            'date' => $date,
            'students' => $students->map(fn (Student $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'admission_number' => $s->admission_number,
                'status' => $records->get($s->id)?->status,
                'absence_alert_sent' => (bool) $records->get($s->id)?->absence_alert_sent_at,
            ]),
        ]);
    }

    /**
     * Month summary for a class: per-student counts and attendance %, plus daily totals.
     */
    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $class = SchoolClass::query()->findOrFail($data['school_class_id']);
        $this->schoolForRoles($request->user(), $class->school_id, ...self::STAFF_ROLES);
        $this->ensureAcademicRefsBelongToSchool($class->school_id, $class->id, $data['section_id'] ?? null);

        $start = Carbon::createFromFormat('Y-m-d', $data['month'].'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();

        $students = $this->roster($class, $data['section_id'] ?? null);

        $records = AttendanceRecord::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['student_id', 'date', 'status']);

        $byStudent = $records->groupBy('student_id');

        $rows = $students->map(function (Student $s) use ($byStudent) {
            $counts = array_fill_keys(self::STATUSES, 0);

            foreach ($byStudent->get($s->id, collect()) as $record) {
                $counts[$record->status] = ($counts[$record->status] ?? 0) + 1;
            }

            $marked = array_sum($counts);
            $attended = $counts['present'] + $counts['late'];

            return [
                'id' => $s->id,
                'name' => $s->name,
                ...$counts,
                'marked_days' => $marked,
                'percent' => $marked > 0 ? round($attended / $marked * 100, 1) : null,
            ];
        })->values();

        $days = $records
            ->groupBy(fn ($r) => $r->date->toDateString())
            ->map(fn ($dayRecords, $date) => [
                'date' => $date,
                ...collect(self::STATUSES)->mapWithKeys(
                    fn ($status) => [$status => $dayRecords->where('status', $status)->count()]
                )->all(),
            ])
            ->sortKeys()
            ->values();

        $totalMarked = $rows->sum('marked_days');
        $totalAttended = $rows->sum('present') + $rows->sum('late');

        return response()->json([
            'month' => $data['month'],
            'class' => $class->name,
            'summary' => [
                'students' => $rows->count(),
                'school_days' => $days->count(),
                'average_percent' => $totalMarked > 0 ? round($totalAttended / $totalMarked * 100, 1) : null,
                'below_75' => $rows->filter(fn ($r) => $r['percent'] !== null && $r['percent'] < 75)->count(),
            ],
            'students' => $rows,
            'days' => $days,
        ]);
    }

    private function roster(SchoolClass $class, ?int $sectionId)
    {
        return Student::query()
            ->where('school_id', $class->school_id)
            ->where('school_class_id', $class->id)
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'admission_number']);
    }
}
