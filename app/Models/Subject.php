<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = ['school_id', 'name', 'code'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function homework(): HasMany
    {
        return $this->hasMany(Homework::class);
    }
}
