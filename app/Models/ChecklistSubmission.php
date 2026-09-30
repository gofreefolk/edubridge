<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistSubmission extends Model
{
    protected $fillable = [
        'checklist_template_id', 'school_id', 'completed_by', 'period_date',
        'responses', 'done_count', 'total_count',
    ];

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'responses' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
