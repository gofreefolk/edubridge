<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationCampaign extends Model
{
    protected $fillable = [
        'school_id', 'created_by', 'title', 'description',
        'goal_amount', 'raised_amount', 'ends_on', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'goal_amount' => 'decimal:2',
            'raised_amount' => 'decimal:2',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
