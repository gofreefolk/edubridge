<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorshipRequest extends Model
{
    protected $fillable = [
        'school_id', 'alumni_user_id', 'student_id', 'topic', 'message', 'status',
    ];

    public function alumni(): BelongsTo
    {
        return $this->belongsTo(User::class, 'alumni_user_id');
    }
}
