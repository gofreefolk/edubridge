<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ChecklistTemplate extends Model
{
    protected $fillable = ['school_id', 'created_by', 'name', 'frequency', 'items', 'is_active'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ChecklistSubmission::class);
    }

    /**
     * The period a date falls in: the day itself, or the Monday of its week.
     */
    public function periodFor(Carbon $date): Carbon
    {
        return $this->frequency === 'weekly'
            ? $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay()
            : $date->copy()->startOfDay();
    }
}
