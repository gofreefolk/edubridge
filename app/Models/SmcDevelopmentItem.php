<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmcDevelopmentItem extends Model
{
    protected $fillable = ['school_id', 'title', 'description', 'status'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
