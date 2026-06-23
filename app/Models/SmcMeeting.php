<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmcMeeting extends Model
{
    protected $fillable = [
        'school_id', 'author_id', 'title', 'agenda', 'minutes',
        'scheduled_at', 'status',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
