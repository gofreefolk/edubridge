<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    protected $fillable = [
        'school_id', 'vehicle_id', 'name', 'direction',
        'default_start_time', 'is_active',
    ];

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('sort_order');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
