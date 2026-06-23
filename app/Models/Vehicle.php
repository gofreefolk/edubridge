<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'school_id', 'registration_number', 'name', 'capacity',
        'driver_user_id', 'is_active',
    ];

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }
}
