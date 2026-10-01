<?php

namespace App\Jobs;

use App\Models\FeeGatewayAccount;
use App\Models\FeePaymentLink;
use App\Services\Fees\RazorpayClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Cancels an invoice's stale payment links at the gateway after its balance changed
 * (office payment, void). A link the gateway refuses to cancel — usually because it was
 * just paid — is left open; its webhook records the payment.
 */
class CancelFeePaymentLinks implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  list<int>  $linkIds  the links open when the balance changed; a link made
     *                              after that (for the new balance) must stay open
     */
    public function __construct(
        public readonly array $linkIds,
    ) {}

    public function handle(RazorpayClient $razorpay): void
    {
        $links = FeePaymentLink::query()
            ->whereIn('id', $this->linkIds)
            ->where('status', 'created')
            ->get();

        foreach ($links as $link) {
            $account = FeeGatewayAccount::query()->where('school_id', $link->school_id)->first();

            if (! $account) {
                continue;
            }

            try {
                $razorpay->cancelPaymentLink($account, $link->gateway_link_id);
                $link->update(['status' => 'cancelled']);
            } catch (RuntimeException) {
                // Already paid / expired at the gateway; the webhook settles its status.
            }
        }
    }
}
