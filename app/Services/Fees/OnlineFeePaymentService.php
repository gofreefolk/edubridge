<?php

namespace App\Services\Fees;

use App\Models\FeeGatewayAccount;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeePaymentLink;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Phase 2b: parents pay an invoice's balance through a Razorpay payment link on the
 * school's own account. A payment is recorded from the signed browser callback or the
 * signed webhook, whichever arrives first; both are idempotent on the gateway payment id.
 */
class OnlineFeePaymentService
{
    public function __construct(
        private readonly RazorpayClient $razorpay,
        private readonly FeeService $fees,
    ) {}

    /** The school's gateway when it is switched on and ready to take payments. */
    public function usableAccount(int $schoolId): ?FeeGatewayAccount
    {
        $account = FeeGatewayAccount::query()->where('school_id', $schoolId)->first();

        return $account?->isUsable() ? $account : null;
    }

    /**
     * A link for the invoice's current balance: the open one if it still matches,
     * otherwise a new one (stale links are cancelled).
     */
    public function linkFor(FeeInvoice $invoice, User $by): FeePaymentLink
    {
        $account = $this->usableAccount($invoice->school_id);

        if (! $account) {
            throw new InvalidArgumentException('fee_online_unavailable');
        }

        $invoice->refresh();
        $balance = $invoice->balancePaise();

        if ($balance <= 0) {
            throw new InvalidArgumentException('fee_nothing_to_pay');
        }

        $open = FeePaymentLink::query()
            ->where('fee_invoice_id', $invoice->id)
            ->where('status', 'created')
            ->latest('id')
            ->get();

        $reusable = $open->first(fn (FeePaymentLink $l) => $l->isReusableFor($balance));
        if ($reusable) {
            return $reusable;
        }

        $this->fees->cancelOpenPaymentLinks($invoice);

        $invoice->loadMissing('school:id,name', 'student:id,name');
        $expiresAt = now()->addHours(config('edubridge.fees.online.link_expiry_hours'));

        $created = $this->razorpay->createPaymentLink($account, [
            'amount' => $balance,
            'currency' => 'INR',
            'accept_partial' => false,
            'reference_id' => 'EB-'.$invoice->id.'-'.now()->format('ymdHis'),
            'description' => mb_substr("{$invoice->school->name}: {$invoice->label} ({$invoice->number}) — {$invoice->student->name}", 0, 255),
            'notify' => ['sms' => false, 'email' => false],
            'reminder_enable' => false,
            'notes' => [
                'school_id' => (string) $invoice->school_id,
                'fee_invoice_id' => (string) $invoice->id,
                'invoice_number' => $invoice->number,
            ],
            'callback_url' => url('/fees/pay/return'),
            'callback_method' => 'get',
            'expire_by' => $expiresAt->getTimestamp(),
        ]);

        return FeePaymentLink::query()->create([
            'school_id' => $invoice->school_id,
            'fee_invoice_id' => $invoice->id,
            'provider' => 'razorpay',
            'gateway_link_id' => $created['id'],
            'short_url' => $created['short_url'],
            'amount_paise' => $balance,
            'status' => 'created',
            'expires_at' => $expiresAt,
            'created_by' => $by->id,
        ]);
    }

    /**
     * Browser return from the payment page. Returns the recorded payment, or null when
     * the parent did not complete the payment.
     */
    public function confirmCallback(array $params): ?FeePayment
    {
        $link = FeePaymentLink::query()
            ->where('provider', 'razorpay')
            ->where('gateway_link_id', (string) ($params['razorpay_payment_link_id'] ?? ''))
            ->first();
        $account = $link ? FeeGatewayAccount::query()->where('school_id', $link->school_id)->first() : null;

        if (! $link || ! $account || ! RazorpayClient::callbackSignatureValid($params, $account->key_secret)) {
            throw new InvalidArgumentException('fee_payment_unverified');
        }

        if (($params['razorpay_payment_link_status'] ?? null) !== 'paid') {
            return null;
        }

        return $this->fees->recordGatewayPayment(
            $link->invoice, $link->amount_paise, (string) $params['razorpay_payment_id'], now(), $link,
        );
    }

    /**
     * Gateway → server notification. Returns false when the signature does not verify.
     */
    public function handleWebhook(School $school, string $body, ?string $signature): bool
    {
        $account = FeeGatewayAccount::query()->where('school_id', $school->id)->first();

        if (! $account?->webhook_secret || ! RazorpayClient::webhookSignatureValid($body, $signature, $account->webhook_secret)) {
            return false;
        }

        $event = json_decode($body, true) ?: [];
        $link = FeePaymentLink::query()
            ->where('school_id', $school->id)
            ->where('provider', 'razorpay')
            ->where('gateway_link_id', (string) data_get($event, 'payload.payment_link.entity.id'))
            ->first();

        if (! $link) {
            return true; // not one of ours (e.g. a link made in the Razorpay dashboard)
        }

        $paymentId = (string) data_get($event, 'payload.payment.entity.id');

        match ($event['event'] ?? null) {
            'payment_link.paid' => $paymentId === '' ? null : $this->fees->recordGatewayPayment(
                $link->invoice,
                (int) data_get($event, 'payload.payment.entity.amount', $link->amount_paise),
                $paymentId,
                Carbon::createFromTimestamp((int) data_get($event, 'payload.payment.entity.created_at', now()->getTimestamp()), config('app.timezone')),
                $link,
            ),
            'payment_link.cancelled', 'payment_link.expired' => FeePaymentLink::query()
                ->whereKey($link->id)
                ->where('status', 'created')
                ->update(['status' => $event['event'] === 'payment_link.expired' ? 'expired' : 'cancelled']),
            default => null,
        };

        return true;
    }
}
