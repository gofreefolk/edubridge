<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A school's own payment gateway account; online fees are paid straight into it.
 */
class FeeGatewayAccount extends Model
{
    protected $fillable = ['school_id', 'provider', 'key_id', 'key_secret', 'webhook_secret', 'is_enabled', 'updated_by'];

    protected $hidden = ['key_secret', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'key_secret' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'is_enabled' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Ready to take payments: switched on and able to verify webhooks. */
    public function isUsable(): bool
    {
        return $this->is_enabled && $this->webhook_secret;
    }
}
