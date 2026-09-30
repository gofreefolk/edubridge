<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $fillable = ['question_bank_id', 'type', 'body', 'options', 'correct_answer', 'marks'];

    // Never sent to students; staff endpoints opt back in with makeVisible().
    protected $hidden = ['correct_answer'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'marks' => 'decimal:2',
        ];
    }

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class);
    }
}
