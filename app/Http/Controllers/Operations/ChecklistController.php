<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Recurring operational checklists (e.g. morning safety walk, weekly hygiene check).
 * Admins define templates; any staff member completes them; admins see completion.
 */
class ChecklistController extends Controller
{
    use AuthorizesSchoolAdmin;

    private const STAFF_ROLES = ['school_admin', 'teacher', 'transport_staff'];

    /**
     * Active checklists with their submission for the period containing `date`.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'include_inactive' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $school = $this->schoolForRoles($user, (int) $data['school_id'], ...self::STAFF_ROLES);
        $isAdmin = $user->hasRoleAtSchool($school->id, 'school_admin');
        $date = Carbon::parse($data['date'] ?? today());

        $templates = ChecklistTemplate::query()
            ->where('school_id', $school->id)
            ->when(! ($isAdmin && $request->boolean('include_inactive')), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        $periods = $templates->mapWithKeys(fn ($t) => [$t->id => $t->periodFor($date)->toDateString()]);

        // Range + filter rather than whereIn on dates: SQLite stores a time part.
        $submissions = ChecklistSubmission::query()
            ->whereIn('checklist_template_id', $templates->pluck('id'))
            ->whereDate('period_date', '>=', $periods->min() ?? $date->toDateString())
            ->whereDate('period_date', '<=', $periods->max() ?? $date->toDateString())
            ->with('completedBy:id,name')
            ->get()
            ->filter(fn ($s) => $s->period_date->toDateString() === $periods->get($s->checklist_template_id))
            ->keyBy('checklist_template_id');

        return response()->json([
            'date' => $date->toDateString(),
            'can_manage' => $isAdmin,
            'checklists' => $templates->map(fn (ChecklistTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'frequency' => $t->frequency,
                'items' => $t->items,
                'is_active' => $t->is_active,
                'period_date' => $t->periodFor($date)->toDateString(),
                'submission' => $this->submissionPayload($submissions->get($t->id)),
            ]),
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $data = $this->validateTemplate($request, requireSchool: true);
        $school = $this->schoolForAdmin($request->user(), (int) $data['school_id']);

        $template = ChecklistTemplate::query()->create([
            'school_id' => $school->id,
            'created_by' => $request->user()->id,
            'name' => $data['name'],
            'frequency' => $data['frequency'],
            'items' => $data['items'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['checklist' => $template], 201);
    }

    public function updateTemplate(Request $request, ChecklistTemplate $template): JsonResponse
    {
        $this->schoolForAdmin($request->user(), $template->school_id);
        $data = $this->validateTemplate($request, requireSchool: false);

        $template->update($data);

        return response()->json(['checklist' => $template->fresh()]);
    }

    public function submit(Request $request, ChecklistTemplate $template): JsonResponse
    {
        $this->schoolForRoles($request->user(), $template->school_id, ...self::STAFF_ROLES);
        abort_unless($template->is_active, 422, __('edubridge.checklist_inactive'));

        $itemCount = count($template->items);

        $data = $request->validate([
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'responses' => ['required', 'array', 'size:'.$itemCount],
            'responses.*.done' => ['required', 'boolean'],
            'responses.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $responses = collect($template->items)->values()->map(fn (string $item, int $i) => [
            'item' => $item,
            'done' => (bool) $data['responses'][$i]['done'],
            'note' => $data['responses'][$i]['note'] ?? null,
        ]);

        $period = $template->periodFor(Carbon::parse($data['date'] ?? today()))->toDateString();

        // whereDate: SQLite stores the cast date with a time part.
        $submission = ChecklistSubmission::query()
            ->where('checklist_template_id', $template->id)
            ->whereDate('period_date', $period)
            ->first()
            ?? new ChecklistSubmission(['checklist_template_id' => $template->id, 'period_date' => $period]);

        $submission->fill([
            'school_id' => $template->school_id,
            'completed_by' => $request->user()->id,
            'responses' => $responses->all(),
            'done_count' => $responses->where('done', true)->count(),
            'total_count' => $itemCount,
        ])->save();

        return response()->json(['submission' => $this->submissionPayload($submission->load('completedBy:id,name'))]);
    }

    /**
     * Completion per checklist over a date range, with the periods that were missed.
     */
    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:today'],
        ]);

        $school = $this->schoolForAdmin($request->user(), (int) $data['school_id']);
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->startOfDay();
        abort_if($from->diffInDays($to) > 370, 422, __('edubridge.range_too_long'));

        $templates = ChecklistTemplate::query()->where('school_id', $school->id)->where('is_active', true)->orderBy('name')->get();

        $submissions = ChecklistSubmission::query()
            ->whereIn('checklist_template_id', $templates->pluck('id'))
            ->whereDate('period_date', '>=', $from->copy()->startOfWeek(Carbon::MONDAY))
            ->whereDate('period_date', '<=', $to)
            ->get()
            ->groupBy('checklist_template_id');

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'checklists' => $templates->map(function (ChecklistTemplate $t) use ($from, $to, $submissions) {
                $expected = $this->expectedPeriods($t, $from, $to);
                $done = $submissions->get($t->id, collect())
                    ->map(fn ($s) => $s->period_date->toDateString());
                $completed = array_values(array_intersect($expected, $done->all()));
                $missed = array_values(array_diff($expected, $done->all()));

                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'frequency' => $t->frequency,
                    'expected' => count($expected),
                    'completed' => count($completed),
                    'percent' => count($expected) ? round(count($completed) / count($expected) * 100, 1) : null,
                    'missed' => array_slice($missed, -31),
                ];
            }),
        ]);
    }

    /**
     * School days only (Mon–Sat, as Kerala schools often work Saturdays) for daily
     * checklists; each week's Monday for weekly ones.
     *
     * @return list<string>
     */
    private function expectedPeriods(ChecklistTemplate $template, Carbon $from, Carbon $to): array
    {
        $periods = [];
        $cursor = $template->periodFor($from);

        while ($cursor->lte($to)) {
            if ($template->frequency === 'weekly') {
                $periods[] = $cursor->toDateString();
                $cursor->addWeek();

                continue;
            }

            if (! $cursor->isSunday()) {
                $periods[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $periods;
    }

    private function validateTemplate(Request $request, bool $requireSchool): array
    {
        return $request->validate([
            'school_id' => [$requireSchool ? 'required' : 'prohibited', 'integer', 'exists:schools,id'],
            'name' => [$requireSchool ? 'required' : 'sometimes', 'string', 'max:255'],
            'frequency' => [$requireSchool ? 'required' : 'sometimes', 'in:daily,weekly'],
            'items' => [$requireSchool ? 'required' : 'sometimes', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function submissionPayload(?ChecklistSubmission $submission): ?array
    {
        if (! $submission) {
            return null;
        }

        return [
            'id' => $submission->id,
            'period_date' => $submission->period_date->toDateString(),
            'responses' => $submission->responses,
            'done_count' => $submission->done_count,
            'total_count' => $submission->total_count,
            'completed_by' => $submission->completedBy?->name,
            'updated_at' => $submission->updated_at?->toIso8601String(),
        ];
    }
}
