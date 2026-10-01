<?php

namespace App\Jobs;

use App\Models\FeeInvoice;
use App\Services\Fees\OnlineFeePaymentService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reminds a student's opted-in parents about an unpaid invoice (upcoming or overdue).
 * The command claims the invoice via last_reminded_at before dispatching.
 */
class SendFeeReminderWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $invoiceId,
    ) {}

    public function handle(WhatsAppService $whatsApp, OnlineFeePaymentService $online): void
    {
        $invoice = FeeInvoice::query()->with(['school', 'student.parents'])->find($this->invoiceId);
        $school = $invoice?->school;

        if (! $invoice || $invoice->balancePaise() === 0 || ! $school?->whatsapp_bridge_enabled) {
            return;
        }

        $message = __($invoice->due_on->lt(today()) ? 'edubridge.fee_overdue' : 'edubridge.fee_reminder', [
            'school' => $school->name,
            'balance' => FeeInvoice::rupees($invoice->balancePaise()),
            'student' => $invoice->student->name,
            'label' => $invoice->label,
            'invoice' => $invoice->number,
            'due' => $invoice->due_on->format('d M Y'),
        ]);

        if ($online->usableAccount($school->id)) {
            $message .= ' '.__('edubridge.fee_pay_online', ['url' => url("/students/{$invoice->student_id}/fees")]);
        }

        foreach ($invoice->student->parents as $parent) {
            $whatsApp->sendToUser($parent, $message, 'fee_reminder', $school->id);
        }
    }
}
