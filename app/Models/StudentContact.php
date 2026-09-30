<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentContact extends Model
{
    protected $fillable = ['student_id', 'name', 'relationship', 'phone', 'is_emergency', 'can_pickup', 'notes'];

    protected function casts(): array
    {
        return [
            'is_emergency' => 'boolean',
            'can_pickup' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
