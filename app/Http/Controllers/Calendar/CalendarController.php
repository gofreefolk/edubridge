<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Services\Calendar\CalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    use AuthorizesSchoolAdmin;

    public function __construct(
        private readonly CalendarService $calendarService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['school_id' => ['required', 'exists:schools,id']]);
        $schoolId = (int) $data['school_id'];
        $user = $request->user();

        abort_unless(
            $user->isSuperAdmin() || $user->rolesAtSchool($schoolId) !== [],
            403,
            __('edubridge.unauthorized_role'),
        );

        $events = $this->calendarService->listForSchool($schoolId);

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
            'description_en' => ['nullable', 'string'],
            'event_type' => ['required', 'string', 'in:holiday,exam,ptm,sports_day,fee_due,cultural_program,meeting,event,other'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['boolean'],
            'audience_type' => ['nullable', 'string', 'in:whole_school,class,section'],
            'school_class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'remind_one_day_before' => ['sometimes', 'boolean'],
        ]);

        $school = $this->schoolForAdmin($request->user(), (int) $data['school_id']);
        $this->ensureAcademicRefsBelongToSchool($school->id, $data['school_class_id'] ?? null, $data['section_id'] ?? null);

        $event = $this->calendarService->create($school, $request->user()->id, $data);

        return response()->json(['event' => $event], 201);
    }
}
