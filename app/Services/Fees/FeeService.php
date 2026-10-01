<?php

namespace App\Services\Fees;

use App\Jobs\CancelFeePaymentLinks;
use App\Jobs\SendFeeReceiptWhatsApp;
use App\Models\AcademicYear;
use App\Models\FeeConcession;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeePaymentLink;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FeeService
{
    public function __construct(
        private readonly FeeNumberService $numbers,
    ) {}

    /**
     * One invoice per active student for a billing label (e.g. "Term 1"), built from
     * the fee structures for that label and the student's concessions. Students who
     * already have a live invoice for the label are skipped, so re-running is safe.
     *
     * @param  list<int>|null  $classIds  limit to these classes; null = every class with a structure
     * @return array{created: int, skipped: int}
     */
    public function generateInvoices(School $school, AcademicYear $year, string $label, ?array $classIds, Carbon $issuedOn, User $by): array
    {
        $structures = FeeStructure::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $year->id)
            ->where('label', $label)
            ->with('feeHead:id,name')
            ->orderBy('id')
            ->get();

        if ($structures->isEmpty()) {
            throw new InvalidArgumentException('fee_no_structures');
        }

        $appliesToAll = $structures->contains(fn ($s) => $s->school_class_id === null);

        $students = Student::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->whereNotNull('school_class_id')
            ->when($classIds, fn ($q) => $q->whereIn('school_class_id', $classIds))
            ->when(! $appliesToAll, fn ($q) => $q->whereIn('school_class_id', $structures->pluck('school_class_id')->filter()))
            ->orderBy('id')
            ->get(['id', 'school_class_id']);

        $existing = FeeInvoice::query()
            ->where('academic_year_id', $year->id)
            ->where('label', $label)
            ->where('status', '!=', 'void')
            ->whereIn('student_id', $students->pluck('id'))
            ->pluck('student_id')
            ->flip();

        $concessions = FeeConcession::query()
            ->where('school_id', $school->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy('student_id');

        $created = 0;
        $skipped = 0;

        foreach ($students as $student) {
            $lines = $structures
                ->filter(fn ($s) => $s->school_class_id === null || $s->school_class_id === $student->school_class_id)
                ->values();

            if ($existing->has($student->id) || $lines->isEmpty()) {
                $skipped++;

                continue;
            }

            $priced = $this->applyConcessions($lines, $concessions->get($student->id, collect()));
            $total = array_sum(array_column($priced, 'amount_paise'));
            $discount = array_sum(array_column($priced, 'discount_paise'));

            if ($total - $discount <= 0) {
                $skipped++; // fully concessioned: nothing to bill

                continue;
            }

            DB::transaction(function () use ($school, $year, $label, $student, $lines, $priced, $total, $discount, $issuedOn, $by) {
                $invoice = FeeInvoice::query()->create([
                    'school_id' => $school->id,
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'number' => $this->numbers->allocate($school, 'invoice', $issuedOn, $year),
                    'label' => $label,
                    'issued_on' => $issuedOn->toDateString(),
                    'due_on' => $lines->min('due_on')->toDateString(),
                    'total_paise' => $total,
                    'discount_paise' => $discount,
                    'status' => 'issued',
                    'created_by' => $by->id,
                ]);

                $invoice->lines()->createMany($priced);
            });

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Percent concessions apply per head (or to every line when head is null); fixed
     * amounts reduce the matching line, or the whole invoice line by line. A line never
     * goes below zero.
     *
     * @param  Collection<int, FeeStructure>  $lines
     * @param  Collection<int, FeeConcession>  $concessions
     * @return list<array{fee_head_id: int, description: string, amount_paise: int, discount_paise: int}>
     */
    private function applyConcessions(Collection $lines, Collection $concessions): array
    {
        $priced = $lines->map(fn (FeeStructure $s) => [
            'fee_head_id' => $s->fee_head_id,
            'description' => $s->feeHead?->name ?? $s->label,
            'amount_paise' => $s->amount_paise,
            'discount_paise' => 0,
        ])->all();

        foreach ($concessions->sortBy(fn ($c) => $c->type === 'percent' ? 0 : 1) as $concession) {
            $remaining = $concession->type === 'amount' ? $concession->value : null;

            foreach ($priced as $i => $line) {
                if ($concession->fee_head_id && $concession->fee_head_id !== $line['fee_head_id']) {
                    continue;
                }

                $open = $line['amount_paise'] - $line['discount_paise'];

                if ($concession->type === 'percent') {
                    $cut = intdiv($line['amount_paise'] * min(100, $concession->value), 100);
                } else {
                    $cut = min($remaining, $open);
                    $remaining -= $cut;
                }

                $priced[$i]['discount_paise'] += min($cut, $open);

                if ($remaining === 0) {
                    break;
                }
            }
        }

        return $priced;
    }

    public function recordPayment(FeeInvoice $invoice, int $amountPaise, string $method, ?string $reference, Carbon $paidAt, User $by): FeePayment
    {
        $payment = DB::transaction(function () use ($invoice, $amountPaise, $method, $reference, $paidAt, $by) {
            $invoice = FeeInvoice::query()->with(['school', 'academicYear'])->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === 'void') {
                throw new InvalidArgumentException('fee_invoice_void');
            }

            if ($amountPaise <= 0 || $amountPaise > $invoice->balancePaise()) {
                throw new InvalidArgumentException('fee_payment_exceeds_balance');
            }

            $payment = FeePayment::query()->create([
                'school_id' => $invoice->school_id,
                'fee_invoice_id' => $invoice->id,
                'receipt_number' => $this->numbers->allocate($invoice->school, 'receipt', $paidAt, $invoice->academicYear),
                'amount_paise' => $amountPaise,
                'method' => $method,
                'reference' => $reference,
                'paid_at' => $paidAt,
                'received_by' => $by->id,
            ]);

            $this->refreshTotals($invoice);

            return $payment;
        });

        SendFeeReceiptWhatsApp::dispatch($payment->id)->afterCommit();
        $this->cancelOpenPaymentLinks($invoice);

        return $payment;
    }

    /**
     * Records money a payment gateway has already taken. Idempotent on the gateway's
     * payment id, so webhook retries and the browser callback can both call it. Unlike
     * office payments it never refuses: the money is in the school's account, so an
     * overpayment or a payment on a voided invoice is recorded for the office to refund.
     * The paid link is closed in the same transaction so it is never cancelled afterwards.
     */
    public function recordGatewayPayment(FeeInvoice $invoice, int $amountPaise, string $gatewayPaymentId, Carbon $paidAt, ?FeePaymentLink $link = null): FeePayment
    {
        $existing = FeePayment::query()->where('gateway_payment_id', $gatewayPaymentId)->first();
        if ($existing) {
            return $existing;
        }

        try {
            $payment = DB::transaction(function () use ($invoice, $amountPaise, $gatewayPaymentId, $paidAt, $link) {
                $invoice = FeeInvoice::query()->with(['school', 'academicYear'])->lockForUpdate()->findOrFail($invoice->id);

                // Re-check under the invoice lock: a concurrent webhook may have just won.
                $existing = FeePayment::query()->where('gateway_payment_id', $gatewayPaymentId)->first();
                if ($existing) {
                    return $existing;
                }

                if ($invoice->status === 'void' || $amountPaise > $invoice->balancePaise()) {
                    logger()->warning('Online fee payment needs a refund or review', [
                        'invoice_id' => $invoice->id,
                        'gateway_payment_id' => $gatewayPaymentId,
                        'amount_paise' => $amountPaise,
                        'balance_paise' => $invoice->balancePaise(),
                        'invoice_status' => $invoice->status,
                    ]);
                }

                $payment = FeePayment::query()->create([
                    'school_id' => $invoice->school_id,
                    'fee_invoice_id' => $invoice->id,
                    'receipt_number' => $this->numbers->allocate($invoice->school, 'receipt', $paidAt, $invoice->academicYear),
                    'amount_paise' => $amountPaise,
                    'method' => 'online',
                    'reference' => $gatewayPaymentId,
                    'gateway_payment_id' => $gatewayPaymentId,
                    'paid_at' => $paidAt,
                    'received_by' => null,
                ]);

                $link?->update(['status' => 'paid', 'fee_payment_id' => $payment->id]);
                $this->refreshTotals($invoice);
                SendFeeReceiptWhatsApp::dispatch($payment->id)->afterCommit();

                return $payment;
            });
        } catch (UniqueConstraintViolationException) {
            return FeePayment::query()->where('gateway_payment_id', $gatewayPaymentId)->firstOrFail();
        }

        $this->cancelOpenPaymentLinks($invoice);

        return $payment;
    }

    /**
     * After the balance changes, open links are for the wrong amount; cancel them so a
     * parent cannot pay twice. A fresh link is made the next time someone pays online.
     */
    public function cancelOpenPaymentLinks(FeeInvoice $invoice): void
    {
        $open = FeePaymentLink::query()->where('fee_invoice_id', $invoice->id)->where('status', 'created')->pluck('id');

        if ($open->isNotEmpty()) {
            CancelFeePaymentLinks::dispatch($open->all())->afterCommit();
        }
    }

    public function voidPayment(FeePayment $payment, string $reason, User $by): FeePayment
    {
        return DB::transaction(function () use ($payment, $reason, $by) {
            $payment = FeePayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->voided_at) {
                throw new InvalidArgumentException('fee_already_void');
            }

            $payment->update(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason]);
            $this->refreshTotals(FeeInvoice::query()->lockForUpdate()->findOrFail($payment->fee_invoice_id));

            return $payment;
        });
    }

    /**
     * Only an invoice with no live payments can be voided; void the payments first.
     */
    public function voidInvoice(FeeInvoice $invoice, string $reason, User $by): FeeInvoice
    {
        return DB::transaction(function () use ($invoice, $reason, $by) {
            $invoice = FeeInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === 'void') {
                throw new InvalidArgumentException('fee_already_void');
            }

            if ($invoice->payments()->whereNull('voided_at')->exists()) {
                throw new InvalidArgumentException('fee_invoice_has_payments');
            }

            $invoice->update([
                'status' => 'void',
                'voided_at' => now(),
                'voided_by' => $by->id,
                'void_reason' => $reason,
            ]);

            $this->cancelOpenPaymentLinks($invoice);

            return $invoice;
        });
    }

    private function refreshTotals(FeeInvoice $invoice): void
    {
        $paid = (int) $invoice->payments()->whereNull('voided_at')->sum('amount_paise');

        $invoice->update([
            'paid_paise' => $paid,
            'status' => match (true) {
                $invoice->status === 'void' => 'void', // late online payment on a voided invoice
                $paid >= $invoice->netPaise() => 'paid',
                $paid > 0 => 'partially_paid',
                default => 'issued',
            },
        ]);
    }
}
