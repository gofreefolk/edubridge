<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetableSlot extends Model
{
    protected $fillable = [
        'school_id', 'school_class_id', 'section_id', 'subject_id', 'teacher_id',
        'day_of_week', 'starts_at', 'ends_at', 'room',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
