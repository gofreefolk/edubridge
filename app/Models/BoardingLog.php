<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingLog extends Model
{
    protected $fillable = ['trip_id', 'student_id', 'route_stop_id', 'action', 'logged_at'];

    protected function casts(): array
    {
        return ['logged_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
