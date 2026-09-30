<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeStructure extends Model
{
    protected $fillable = [
        'school_id', 'academic_year_id', 'school_class_id', 'fee_head_id',
        'label', 'amount_paise', 'due_on',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'due_on' => 'date',
        ];
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }
}
