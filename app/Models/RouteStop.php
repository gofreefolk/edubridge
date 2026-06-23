<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteStop extends Model
{
    protected $fillable = ['route_id', 'name', 'sort_order', 'scheduled_time', 'latitude', 'longitude'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}
