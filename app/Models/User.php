<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'preferred_locale', 'large_text_mode'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'large_text_mode' => 'boolean',
        ];
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function noticeReads(): HasMany
    {
        return $this->hasMany(NoticeRead::class);
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'parent_user_id', 'student_id')
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    public function whatsappOptIns(): HasMany
    {
        return $this->hasMany(WhatsAppOptIn::class);
    }

    public function roleAtSchool(int $schoolId): ?string
    {
        $pivot = $this->schools()->where('school_id', $schoolId)->first()?->pivot;

        return $pivot?->role;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->schools()
            ->wherePivot('is_active', true)
            ->get()
            ->pluck('pivot.role')
            ->unique()
            ->values()
            ->all();
    }

    public function hasAnyRole(string ...$roles): bool
    {
        if (in_array('super_admin', $this->getRoles(), true)) {
            return true;
        }

        return count(array_intersect($this->getRoles(), $roles)) > 0;
    }

    public function primaryRole(): string
    {
        $priority = [
            'super_admin',
            'school_admin',
            'teacher',
            'transport_staff',
            'smc_member',
            'parent',
            'grandparent',
            'student',
            'alumni',
        ];

        $roles = $this->getRoles();

        foreach ($priority as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return $roles[0] ?? 'parent';
    }
}
