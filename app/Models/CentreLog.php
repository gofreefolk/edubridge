<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentreLog extends Model
{
    public const CATEGORIES = ['daily', 'incident', 'health', 'behaviour', 'other'];

    protected $fillable = [
        'school_id', 'school_class_id', 'student_id', 'author_id',
        'category', 'title', 'body', 'occurred_at', 'visible_to_parents',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'visible_to_parents' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }
}
