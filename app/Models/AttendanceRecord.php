<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = ['school_id', 'student_id', 'marked_by', 'date', 'status', 'note', 'absence_alert_sent_at'];

    protected function casts(): array
    {
        return ['date' => 'date', 'absence_alert_sent_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
