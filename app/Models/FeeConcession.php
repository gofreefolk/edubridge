<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeConcession extends Model
{
    public const TYPES = ['percent', 'amount'];

    protected $fillable = ['school_id', 'student_id', 'fee_head_id', 'type', 'value', 'reason', 'approved_by'];

    protected function casts(): array
    {
        return ['value' => 'integer'];
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
