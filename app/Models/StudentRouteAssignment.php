<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRouteAssignment extends Model
{
    protected $fillable = ['student_id', 'route_id', 'route_stop_id'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class);
    }
}
