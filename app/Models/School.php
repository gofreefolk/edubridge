<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'district',
        'type',
        'whatsapp_bridge_enabled',
        'settings',
        'is_active',
        'approval_status',
        'admin_contact_name',
        'admin_contact_phone',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_bridge_enabled' => 'boolean',
            'settings' => 'array',
            'is_active' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function notices(): HasMany
    {
        return $this->hasMany(Notice::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function smcMembers(): HasMany
    {
        return $this->hasMany(SmcMember::class);
    }

    public function adminInvites(): HasMany
    {
        return $this->hasMany(SchoolAdminInvite::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }
}
