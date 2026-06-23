<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    protected $fillable = [
        'route_id', 'vehicle_id', 'driver_user_id', 'trip_date',
        'status', 'started_at', 'completed_at', 'delay_note',
    ];

    protected function casts(): array
    {
        return [
            'trip_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function boardingLogs(): HasMany
    {
        return $this->hasMany(BoardingLog::class);
    }
}
