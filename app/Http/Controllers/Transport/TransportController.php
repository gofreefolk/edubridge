<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Models\BoardingLog;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Student;
use App\Models\StudentRouteAssignment;
use App\Models\TransportAbsence;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransportController extends Controller
{
    use AuthorizesSchoolAdmin;

    private const STAFF_ROLES = ['transport_staff', 'school_admin'];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['school_id' => ['required', 'exists:schools,id']]);
        $school = $this->schoolForRoles($request->user(), (int) $data['school_id'], ...self::STAFF_ROLES);

        return response()->json([
            'vehicles' => Vehicle::query()->where('school_id', $school->id)->where('is_active', true)->get(),
            'routes' => Route::query()->where('school_id', $school->id)->with('stops')->get(),
        ]);
    }

    public function studentStatus(Request $request): JsonResponse
    {
        $data = $request->validate(['student_id' => ['required', 'integer']]);
        $student = Student::query()->findOrFail($data['student_id']);
        $this->ensureCanAccessStudent($request->user(), $student);

        $assignment = StudentRouteAssignment::query()
            ->where('student_id', $student->id)
            ->with([
                'route.stops',
                'routeStop',
                'route.trips' => fn ($q) => $q->whereDate('trip_date', today()),
            ])
            ->first();

        return response()->json([
            'student_id' => $student->id,
            'route' => $assignment?->route,
            'stop' => $assignment?->routeStop,
            'today_trip' => $assignment?->route?->trips->first(),
        ]);
    }

    public function reportAbsence(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'absence_date' => ['required', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $student = Student::query()->findOrFail($data['student_id']);
        abort_unless($request->user()->isParentOf($student), 403, __('edubridge.forbidden'));

        $absence = TransportAbsence::query()->updateOrCreate(
            ['student_id' => $student->id, 'absence_date' => $data['absence_date']],
            ['reported_by' => $request->user()->id, 'note' => $data['note'] ?? null],
        );

        return response()->json(['absence' => $absence]);
    }

    public function startTrip(Request $request): JsonResponse
    {
        $data = $request->validate([
            'route_id' => ['required', 'integer'],
            'vehicle_id' => ['nullable', 'integer'],
        ]);

        $route = Route::query()->findOrFail($data['route_id']);
        $this->schoolForRoles($request->user(), $route->school_id, ...self::STAFF_ROLES);

        if (! empty($data['vehicle_id'])
            && ! Vehicle::query()->whereKey($data['vehicle_id'])->where('school_id', $route->school_id)->exists()) {
            abort(422, __('edubridge.invalid_reference'));
        }

        $trip = Trip::query()->create([
            'route_id' => $route->id,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'driver_user_id' => $request->user()->id,
            'trip_date' => today(),
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return response()->json(['trip' => $trip], 201);
    }

    public function logBoarding(Request $request, Trip $trip): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'route_stop_id' => ['nullable', 'integer'],
            'action' => ['required', 'in:boarded,alighted,absent'],
        ]);

        $schoolId = $this->authorizeTrip($request, $trip);

        if (! Student::query()->whereKey($data['student_id'])->where('school_id', $schoolId)->exists()) {
            abort(422, __('edubridge.invalid_reference'));
        }

        if (! empty($data['route_stop_id'])
            && ! RouteStop::query()->whereKey($data['route_stop_id'])->where('route_id', $trip->route_id)->exists()) {
            abort(422, __('edubridge.invalid_reference'));
        }

        $log = BoardingLog::query()->create([
            'trip_id' => $trip->id,
            'student_id' => $data['student_id'],
            'route_stop_id' => $data['route_stop_id'] ?? null,
            'action' => $data['action'],
            'logged_at' => now(),
        ]);

        return response()->json(['log' => $log]);
    }

    public function delayAlert(Request $request, Trip $trip): JsonResponse
    {
        $data = $request->validate(['delay_note' => ['required', 'string', 'max:500']]);
        $this->authorizeTrip($request, $trip);

        $trip->update(['delay_note' => $data['delay_note']]);

        return response()->json(['trip' => $trip]);
    }

    /**
     * @return int the trip's school id
     */
    private function authorizeTrip(Request $request, Trip $trip): int
    {
        $schoolId = (int) $trip->route()->value('school_id');
        $this->schoolForRoles($request->user(), $schoolId, ...self::STAFF_ROLES);

        return $schoolId;
    }
}
