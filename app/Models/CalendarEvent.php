<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarEvent extends Model
{
    protected $fillable = [
        'school_id', 'author_id', 'title', 'description', 'title_en', 'description_en',
        'event_type', 'starts_at', 'ends_at', 'all_day', 'audience_type',
        'school_class_id', 'section_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(EventReminder::class);
    }
}
