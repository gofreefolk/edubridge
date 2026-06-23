<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkSubmission extends Model
{
    protected $fillable = ['homework_id', 'student_id', 'status', 'note', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }
}
