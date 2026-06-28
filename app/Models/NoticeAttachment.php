<?php

namespace App\Models;

use App\Services\Storage\AttachmentStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeAttachment extends Model
{
    protected $fillable = ['notice_id', 'filename', 'path', 'disk', 'mime_type', 'size'];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }

    public function url(): string
    {
        return app(AttachmentStorageService::class)->url($this);
    }
}
