<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FeeConcession;
use App\Models\FeeHead;
use App\Models\FeeNumberSequence;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Fees\FeeNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Admin set-up for fees: heads (Tuition, Bus…), per-class amounts per billing period,
 * invoice / receipt numbering, and per-student concessions.
 */
class FeeSetupController extends Controller
{
    use AuthorizesSchoolAdmin;

    public function __construct(
        private readonly FeeNumberService $numbers,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'academic_year_id' => ['nullable', 'integer'],
        ]);

        $school = $this->schoolForAdmin($request->user(), (int) $data['school_id']);

        $years = AcademicYear::query()
            ->where('school_id', $school->id)
            ->orderByDesc('starts_on')
            ->get(['id', 'name', 'is_current']);

        $year = isset($data['academic_year_id'])
            ? $years->firstWhere('id', (int) $data['academic_year_id'])
            : ($years->firstWhere('is_current', true) ?? $years->first());

        abort_if(isset($data['academic_year_id']) && ! $year, 422, __('edubridge.invalid_reference'));

        $structures = $year
            ? FeeStructure::query()
                ->where('school_id', $school->id)
                ->where('academic_year_id', $year->id)
                ->orderBy('due_on')
                ->orderBy('label')
                ->orderBy('id')
                ->get()
            : collect();

        return response()->json([
            'academic_years' => $years,
            'academic_year_id' => $year?->id,
            'classes' => SchoolClass::query()
                ->where('school_id', $school->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'academic_year_id']),
            'heads' => FeeHead::query()->where('school_id', $school->id)->orderBy('name')->get(['id', 'name', 'is_active']),
            'structures' => $structures->map(fn (FeeStructure $s) => [
                'id' => $s->id,
                'school_class_id' => $s->school_class_id,
                'fee_head_id' => $s->fee_head_id,
                'label' => $s->label,
                'amount_paise' => $s->amount_paise,
                'due_on' => $s->due_on->toDateString(),
            ]),
            'labels' => $structures->pluck('label')->unique()->values(),
            'numbering' => [
                'invoice' => $this->numbers->settings($school, 'invoice'),
                'receipt' => $this->numbers->settings($school, 'receipt'),
            ],
        ]);
    }

    public function storeHead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $school = $this->schoolForAdmin($request->user(), (int) $data['school_id']);
        $request->validate(['name' => [Rule::unique('fee_heads', 'name')->where('school_id', $school->id)]]);

        $head = FeeHead::query()->create(['school_id' => $school->id, 'name' => $data['name']]);

        return response()->json(['head' => $head->only(['id', 'name', 'is_active'])], 201);
    }

    public function updateHead(Request $request, FeeHead $head): JsonResponse
    {
        $this->schoolForAdmin($request->user(), $head->school_id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('fee_heads', 'name')->where('school_id', $head->school_id)->ignore($head->id)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $head->update($data);

        return response()->json(['head' => $head->only(['id', 'name', 'is_active'])]);
    }

    public function storeStructure(Request $request): JsonResponse
    {
        $data = $this->validateStructure($request, creating: true);
        $school = $this->schoolForAdmin($request->user(), (int) $data['school_id']);
        $this->ensureStructureRefs($school, $data);

        $structure = FeeStructure::query()->create([...$data, 'school_id' => $school->id]);

        return response()->json(['structure' => $structure], 201);
    }

    public function updateStructure(Request $request, FeeStructure $structure): JsonResponse
    {
        $school = $this->schoolForAdmin($request->user(), $structure->school_id);
        $data = $this->validateStructure($request, creating: false);
        $this->ensureStructureRefs($school, [...$structure->only(['academic_year_id']), ...$data]);

        // Changes apply to invoices generated from now on; issued invoices keep their lines.
        $structure->update($data);

        return response()->json(['structure' => $structure->fresh()]);
    }

    public function destroyStructure(Request $request, FeeStructure $structure): JsonResponse
    {
        $this->schoolForAdmin($request->user(), $structure->school_id);
        $structure->delete();

        return response()->json(['ok' => true]);
    }

    public function updateNumbering(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'type' => ['required', Rule::in(FeeNumberSequence::TYPES)],
            'format' => ['required', 'string', 'max:60'],
            'reset' => ['required', Rule::in(FeeNumberSequence::RESETS)],
            'next_number' => ['nullable', 'integer', 'min:1', 'max:99999999'],
        ]);

        $school = $this->schoolForAdmin($request->user(), (int) $data['school_id']);

        try {
            $settings = $this->numbers->update($school, $data['type'], $data['format'], $data['reset'], $data['next_number'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => __('edubridge.'.$e->getMessage())], 422);
        }

        return response()->json(['numbering' => $settings]);
    }

    public function concessions(Request $request, Student $student): JsonResponse
    {
        $this->schoolForAdmin($request->user(), $student->school_id);

        return response()->json(['concessions' => $this->concessionList($student)]);
    }

    public function storeConcession(Request $request, Student $student): JsonResponse
    {
        $this->schoolForAdmin($request->user(), $student->school_id);

        $data = $request->validate([
            'fee_head_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::in(FeeConcession::TYPES)],
            'value' => ['required', 'integer', 'min:1', $request->input('type') === 'percent' ? 'max:100' : 'max:10000000000'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if (! empty($data['fee_head_id']) && ! FeeHead::query()->whereKey($data['fee_head_id'])->where('school_id', $student->school_id)->exists()) {
            abort(422, __('edubridge.invalid_reference'));
        }

        FeeConcession::query()->create([
            ...$data,
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'approved_by' => $request->user()->id,
        ]);

        return response()->json(['concessions' => $this->concessionList($student)], 201);
    }

    public function destroyConcession(Request $request, Student $student, FeeConcession $concession): JsonResponse
    {
        $this->schoolForAdmin($request->user(), $student->school_id);
        abort_unless($concession->student_id === $student->id, 404);

        $concession->delete();

        return response()->json(['concessions' => $this->concessionList($student)]);
    }

    private function concessionList(Student $student)
    {
        return FeeConcession::query()
            ->where('student_id', $student->id)
            ->with(['feeHead:id,name', 'approvedBy:id,name'])
            ->orderBy('id')
            ->get()
            ->map(fn (FeeConcession $c) => [
                'id' => $c->id,
                'fee_head_id' => $c->fee_head_id,
                'fee_head' => $c->feeHead?->name,
                'type' => $c->type,
                'value' => $c->value,
                'reason' => $c->reason,
                'approved_by' => $c->approvedBy?->name,
            ]);
    }

    private function validateStructure(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'school_id' => [$creating ? 'required' : 'prohibited', 'integer', 'exists:schools,id'],
            'academic_year_id' => [$creating ? 'required' : 'prohibited', 'integer'],
            'school_class_id' => ['nullable', 'integer'],
            'fee_head_id' => [$required, 'integer'],
            'label' => [$required, 'string', 'max:100'],
            'amount_paise' => [$required, 'integer', 'min:1', 'max:10000000000'],
            'due_on' => [$required, 'date'],
        ]);
    }

    private function ensureStructureRefs(School $school, array $data): void
    {
        $ok = (! isset($data['academic_year_id']) || AcademicYear::query()->whereKey($data['academic_year_id'])->where('school_id', $school->id)->exists())
            && (! isset($data['fee_head_id']) || FeeHead::query()->whereKey($data['fee_head_id'])->where('school_id', $school->id)->exists())
            && (empty($data['school_class_id']) || SchoolClass::query()->whereKey($data['school_class_id'])->where('school_id', $school->id)->exists());

        abort_unless($ok, 422, __('edubridge.invalid_reference'));
    }
}
