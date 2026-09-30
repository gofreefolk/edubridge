<?php

namespace App\Jobs;

use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a payment receipt to the student's opted-in parents.
 */
class SendFeeReceiptWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $paymentId,
    ) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $payment = FeePayment::query()
            ->with(['invoice.school', 'invoice.student.parents'])
            ->find($this->paymentId);

        $invoice = $payment?->invoice;
        $school = $invoice?->school;

        if (! $payment || $payment->voided_at || ! $school?->whatsapp_bridge_enabled) {
            return;
        }

        $message = __('edubridge.fee_receipt', [
            'school' => $school->name,
            'amount' => FeeInvoice::rupees($payment->amount_paise),
            'student' => $invoice->student->name,
            'label' => $invoice->label,
            'receipt' => $payment->receipt_number,
            'balance' => FeeInvoice::rupees($invoice->balancePaise()),
        ]);

        foreach ($invoice->student->parents as $parent) {
            $whatsApp->sendToUser($parent, $message, 'fee_receipt', $school->id);
        }
    }
}
