<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeAttachment extends Model
{
    protected $fillable = ['notice_id', 'filename', 'path', 'mime_type', 'size'];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }
}
