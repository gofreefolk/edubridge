<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeAudience extends Model
{
    protected $fillable = ['notice_id', 'school_class_id', 'section_id'];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }
}
