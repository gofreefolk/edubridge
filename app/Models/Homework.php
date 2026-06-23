<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Homework extends Model
{
    protected $table = 'homework';

    protected $fillable = [
        'school_id', 'school_class_id', 'section_id', 'subject_id', 'teacher_id',
        'title', 'description', 'due_date', 'attachment_path',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }
}
