<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\Calendar\CalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(
        private readonly CalendarService $calendarService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['school_id' => ['required', 'exists:schools,id']]);

        $events = $this->calendarService->listForSchool((int) $request->query('school_id'));

        return response()->json([
            'events' => $events->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'title_en' => $e->title_en,
                'description' => $e->description,
                'event_type' => $e->event_type,
                'starts_at' => $e->starts_at->toIso8601String(),
                'ends_at' => $e->ends_at?->toIso8601String(),
                'all_day' => $e->all_day,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'event_type' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'all_day' => ['boolean'],
            'audience_type' => ['nullable', 'string'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
        ]);

        $school = School::query()->findOrFail($data['school_id']);
        $event = $this->calendarService->create($school, $request->user()->id, $data);

        return response()->json(['event' => $event], 201);
    }
}
