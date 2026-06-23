<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlumniEvent extends Model
{
    protected $fillable = [
        'school_id', 'created_by', 'title', 'description',
        'starts_at', 'location', 'batch_year',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
