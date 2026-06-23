<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppOptIn extends Model
{
    protected $table = 'whatsapp_opt_ins';

    protected $fillable = ['user_id', 'school_id', 'opted_in', 'opted_in_at'];

    protected function casts(): array
    {
        return [
            'opted_in' => 'boolean',
            'opted_in_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
