<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\BoardingLog;
use App\Models\Route;
use App\Models\Student;
use App\Models\StudentRouteAssignment;
use App\Models\TransportAbsence;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schoolId = (int) $request->query('school_id');
        $request->validate(['school_id' => ['required', 'exists:schools,id']]);

        return response()->json([
            'vehicles' => Vehicle::query()->where('school_id', $schoolId)->where('is_active', true)->get(),
            'routes' => Route::query()->where('school_id', $schoolId)->with('stops')->get(),
        ]);
    }

    public function studentStatus(Request $request): JsonResponse
    {
        $student = Student::query()->findOrFail($request->query('student_id'));

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
            'student_id' => ['required', 'exists:students,id'],
            'absence_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $absence = TransportAbsence::query()->updateOrCreate(
            ['student_id' => $data['student_id'], 'absence_date' => $data['absence_date']],
            ['reported_by' => $request->user()->id, 'note' => $data['note'] ?? null],
        );

        return response()->json(['absence' => $absence]);
    }

    public function startTrip(Request $request): JsonResponse
    {
        $data = $request->validate([
            'route_id' => ['required', 'exists:routes,id'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
        ]);

        $trip = Trip::query()->create([
            'route_id' => $data['route_id'],
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
            'student_id' => ['required', 'exists:students,id'],
            'route_stop_id' => ['nullable', 'exists:route_stops,id'],
            'action' => ['required', 'in:boarded,alighted,absent'],
        ]);

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
        $data = $request->validate(['delay_note' => ['required', 'string']]);
        $trip->update(['delay_note' => $data['delay_note']]);

        return response()->json(['trip' => $trip]);
    }
}
