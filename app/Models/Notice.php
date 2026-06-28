<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Notice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id',
        'author_id',
        'title',
        'body',
        'title_en',
        'body_en',
        'priority',
        'audience_type',
        'pinned_until',
        'published_at',
        'scheduled_publish_at',
        'whatsapp_sent_at',
        'magic_link_token',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'pinned_until' => 'datetime',
            'published_at' => 'datetime',
            'scheduled_publish_at' => 'datetime',
            'whatsapp_sent_at' => 'datetime',
        ];
    }

    public static function generateMagicLinkToken(): string
    {
        do {
            $token = Str::lower(Str::random(12));
        } while (static::where('magic_link_token', $token)->exists());

        return $token;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(NoticeAudience::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NoticeAttachment::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NoticeRead::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereNotNull('magic_link_token');
    }
}
