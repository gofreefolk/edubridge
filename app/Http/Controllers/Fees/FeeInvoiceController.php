<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Fees\FeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class FeeInvoiceController extends Controller
{
    use AuthorizesSchoolAdmin;

    public function __construct(
        private readonly FeeService $fees,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'academic_year_id' => ['nullable', 'integer'],
            'school_class_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([...FeeInvoice::STATUSES, 'unpaid', 'overdue'])],
            'label' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $school = $this->schoolForAdmin($request->user(), (int) $data['school_id']);
        $this->ensureAcademicRefsBelongToSchool($school->id, $data['school_class_id'] ?? null);

        $invoices = FeeInvoice::query()
            ->where('school_id', $school->id)
            ->when($data['academic_year_id'] ?? null, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($data['label'] ?? null, fn ($q, $label) => $q->where('label', $label))
            ->when($data['school_class_id'] ?? null, fn ($q, $id) => $q->whereHas('student', fn ($s) => $s->where('school_class_id', $id)))
            ->when($data['status'] ?? null, function ($q, $status) {
                match ($status) {
                    'unpaid' => $q->whereIn('status', ['issued', 'partially_paid']),
                    'overdue' => $q->whereIn('status', ['issued', 'partially_paid'])->whereDate('due_on', '<', today()),
                    default => $q->where('status', $status),
                };
            })
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$term}%")
                ->orWhereHas('student', fn ($s) => $s->where('name', 'like', "%{$term}%")->orWhere('admission_number', 'like', "%{$term}%"))))
            ->with('student:id,name,admission_number,school_class_id', 'student.schoolClass:id,name')
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'invoices' => collect($invoices->items())->map(fn (FeeInvoice $i) => $this->summary($i)),
            'meta' => ['current_page' => $invoices->currentPage(), 'last_page' => $invoices->lastPage(), 'total' => $invoices->total()],
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'academic_year_id' => ['required', 'integer'],
            'label' => ['required', 'string', 'max:100'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer'],
            'issued_on' => ['nullable', 'date'],
        ]);

        $school = $this->schoolForAdmin($request->user(), (int) $data['school_id']);
        $year = AcademicYear::query()->whereKey($data['academic_year_id'])->where('school_id', $school->id)->first();
        abort_unless($year, 422, __('edubridge.invalid_reference'));

        $classIds = $data['class_ids'] ?? null;
        if ($classIds && SchoolClass::query()->whereIn('id', $classIds)->where('school_id', $school->id)->count() !== count(array_unique($classIds))) {
            abort(422, __('edubridge.invalid_reference'));
        }

        try {
            $result = $this->fees->generateInvoices(
                $school, $year, $data['label'], $classIds ?: null,
                Carbon::parse($data['issued_on'] ?? today()), $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e);
        }

        return response()->json($result);
    }

    public function show(Request $request, FeeInvoice $invoice): JsonResponse
    {
        $this->authorizeView($request->user(), $invoice->student);

        return response()->json(['invoice' => $this->detail($invoice)]);
    }

    public function void(Request $request, FeeInvoice $invoice): JsonResponse
    {
        $this->schoolForAdmin($request->user(), $invoice->school_id);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $invoice = $this->fees->voidInvoice($invoice, $data['reason'], $request->user());
        } catch (InvalidArgumentException $e) {
            return $this->error($e);
        }

        return response()->json(['invoice' => $this->detail($invoice)]);
    }

    public function storePayment(Request $request, FeeInvoice $invoice): JsonResponse
    {
        $this->schoolForAdmin($request->user(), $invoice->school_id);

        $data = $request->validate([
            'amount_paise' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(FeePayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        try {
            $payment = $this->fees->recordPayment(
                $invoice, $data['amount_paise'], $data['method'], $data['reference'] ?? null,
                isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(), $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e);
        }

        return response()->json([
            'payment_id' => $payment->id,
            'receipt_number' => $payment->receipt_number,
            'invoice' => $this->detail($invoice),
        ], 201);
    }

    public function voidPayment(Request $request, FeePayment $payment): JsonResponse
    {
        $this->schoolForAdmin($request->user(), $payment->school_id);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $this->fees->voidPayment($payment, $data['reason'], $request->user());
        } catch (InvalidArgumentException $e) {
            return $this->error($e);
        }

        return response()->json(['invoice' => $this->detail($payment->invoice)]);
    }

    /**
     * Printable receipt, for the office and for the student's parents.
     */
    public function receipt(Request $request, FeePayment $payment): JsonResponse
    {
        $invoice = $payment->invoice()->with(['school', 'student.schoolClass', 'student.section', 'lines', 'academicYear'])->firstOrFail();
        $this->authorizeView($request->user(), $invoice->student);

        return response()->json([
            'receipt' => [
                'receipt_number' => $payment->receipt_number,
                'paid_at' => $payment->paid_at->toIso8601String(),
                'amount_paise' => $payment->amount_paise,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'received_by' => $payment->receivedBy?->name,
                'voided' => (bool) $payment->voided_at,
                'void_reason' => $payment->void_reason,
                'school' => ['name' => $invoice->school->name, 'code' => $invoice->school->code, 'district' => $invoice->school->district],
                'student' => [
                    'name' => $invoice->student->name,
                    'admission_number' => $invoice->student->admission_number,
                    'class' => trim(($invoice->student->schoolClass?->name ?? '').' '.($invoice->student->section?->name ?? '')),
                ],
                'invoice' => [
                    'number' => $invoice->number,
                    'label' => $invoice->label,
                    'academic_year' => $invoice->academicYear?->name,
                    'net_paise' => $invoice->netPaise(),
                    'paid_paise' => $invoice->paid_paise,
                    'balance_paise' => $invoice->balancePaise(),
                ],
            ],
        ]);
    }

    /**
     * All invoices and payments for one student: the office's ledger and the parent's fees tab.
     */
    public function ledger(Request $request, Student $student): JsonResponse
    {
        $this->authorizeView($request->user(), $student);

        $invoices = FeeInvoice::query()
            ->where('student_id', $student->id)
            ->with(['lines', 'payments', 'academicYear:id,name'])
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->get();

        $live = $invoices->where('status', '!=', 'void');

        return response()->json([
            'student' => ['id' => $student->id, 'name' => $student->name, 'admission_number' => $student->admission_number],
            'totals' => [
                'net_paise' => $live->sum(fn ($i) => $i->netPaise()),
                'paid_paise' => $live->sum('paid_paise'),
                'balance_paise' => $live->sum(fn ($i) => $i->balancePaise()),
                'overdue_paise' => $live->filter(fn ($i) => $i->due_on->lt(today()))->sum(fn ($i) => $i->balancePaise()),
            ],
            'invoices' => $invoices->map(fn (FeeInvoice $i) => $this->detail($i, withStudent: false)),
        ]);
    }

    private function authorizeView(User $user, Student $student): void
    {
        $allowed = $user->hasRoleAtSchool($student->school_id, 'school_admin')
            || $user->isParentOf($student)
            || ($student->user_id !== null && $student->user_id === $user->id);

        abort_unless($allowed, 403, __('edubridge.forbidden'));
    }

    private function summary(FeeInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'label' => $invoice->label,
            'status' => $invoice->status,
            'issued_on' => $invoice->issued_on->toDateString(),
            'due_on' => $invoice->due_on->toDateString(),
            'overdue' => $invoice->balancePaise() > 0 && $invoice->due_on->lt(today()),
            'total_paise' => $invoice->total_paise,
            'discount_paise' => $invoice->discount_paise,
            'net_paise' => $invoice->netPaise(),
            'paid_paise' => $invoice->paid_paise,
            'balance_paise' => $invoice->balancePaise(),
            'student' => $invoice->relationLoaded('student') ? [
                'id' => $invoice->student->id,
                'name' => $invoice->student->name,
                'admission_number' => $invoice->student->admission_number,
                'class' => $invoice->student->schoolClass?->name,
            ] : null,
        ];
    }

    private function detail(FeeInvoice $invoice, bool $withStudent = true): array
    {
        $invoice = $invoice->fresh(['lines', 'payments.receivedBy:id,name', 'academicYear:id,name', ...($withStudent ? ['student.schoolClass:id,name'] : [])]);

        return [
            ...$this->summary($invoice),
            'academic_year' => $invoice->academicYear?->name,
            'void_reason' => $invoice->void_reason,
            'lines' => $invoice->lines->map(fn ($l) => $l->only(['id', 'description', 'amount_paise', 'discount_paise'])),
            'payments' => $invoice->payments->map(fn (FeePayment $p) => [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number,
                'amount_paise' => $p->amount_paise,
                'method' => $p->method,
                'reference' => $p->reference,
                'paid_at' => $p->paid_at->toIso8601String(),
                'received_by' => $p->receivedBy?->name,
                'voided' => (bool) $p->voided_at,
                'void_reason' => $p->void_reason,
            ]),
        ];
    }

    private function error(InvalidArgumentException $e): JsonResponse
    {
        return response()->json(['message' => __('edubridge.'.$e->getMessage())], 422);
    }
}
