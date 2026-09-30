<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeNumberSequence extends Model
{
    public const TYPES = ['invoice', 'receipt'];

    public const RESETS = ['never', 'yearly', 'academic_year'];

    public const DEFAULT_FORMATS = [
        'invoice' => 'INV/{AY}/{SEQ:4}',
        'receipt' => 'RCT/{AY}/{SEQ:4}',
    ];

    protected $fillable = ['school_id', 'type', 'format', 'reset', 'period', 'next_number'];

    protected function casts(): array
    {
        return ['next_number' => 'integer'];
    }
}
