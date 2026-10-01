<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeePaymentLink extends Model
{
    protected $fillable = [
        'school_id', 'fee_invoice_id', 'provider', 'gateway_link_id', 'short_url', 'amount_paise',
        'status', 'expires_at', 'fee_payment_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
    }

    /** Still payable as-is: open, not expiring in the next few minutes, and for this amount. */
    public function isReusableFor(int $amountPaise): bool
    {
        return $this->status === 'created'
            && $this->amount_paise === $amountPaise
            && ($this->expires_at === null || $this->expires_at->gt(now()->addMinutes(10)));
    }
}
