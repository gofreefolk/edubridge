<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password', 'phone', 'preferred_locale', 'large_text_mode'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_PRIORITY = [
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

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'large_text_mode' => 'boolean',
            'last_seen_at' => 'datetime',
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

    /**
     * Highest-priority active role the user holds at the school.
     */
    public function roleAtSchool(int $schoolId): ?string
    {
        return self::highestPriorityRole($this->rolesAtSchool($schoolId));
    }

    /** @return list<string> */
    public function rolesAtSchool(int $schoolId): array
    {
        return $this->activeRoleRows()
            ->where('school_user.school_id', $schoolId)
            ->pluck('school_user.role')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * True when the user holds any of the given roles at this specific school.
     * Super admins always pass.
     */
    public function hasRoleAtSchool(int $schoolId, string ...$roles): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return count(array_intersect($this->rolesAtSchool($schoolId), $roles)) > 0;
    }

    /**
     * Grants a role at a school without touching the user's other roles there.
     */
    public function assignSchoolRole(int $schoolId, string $role, bool $active = true): void
    {
        DB::table('school_user')->updateOrInsert(
            ['school_id' => $schoolId, 'user_id' => $this->id, 'role' => $role],
            ['is_active' => $active, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function isParentOf(Student|int $student): bool
    {
        $studentId = $student instanceof Student ? $student->id : $student;

        return $this->children()->where('students.id', $studentId)->exists();
    }

    /**
     * Whether the user may see a student's records: the student themselves, a linked
     * parent, staff at the student's school, or a super admin.
     */
    public function canAccessStudent(Student $student): bool
    {
        if ($student->user_id !== null && $student->user_id === $this->id) {
            return true;
        }

        if ($this->hasRoleAtSchool($student->school_id, 'school_admin', 'teacher')) {
            return true;
        }

        return $this->isParentOf($student);
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->activeRoleRows()
            ->pluck('school_user.role')
            ->unique()
            ->values()
            ->all();
    }

    public function isSuperAdmin(): bool
    {
        return $this->activeRoleRows()
            ->whereNull('school_user.school_id')
            ->where('school_user.role', 'super_admin')
            ->exists();
    }

    public function hasAnyRole(string ...$roles): bool
    {
        $userRoles = $this->getRoles();

        if (in_array('super_admin', $userRoles, true)) {
            return true;
        }

        return count(array_intersect($userRoles, $roles)) > 0;
    }

    public function primaryRole(): string
    {
        $roles = $this->getRoles();

        return self::highestPriorityRole($roles) ?? 'parent';
    }

    /** @param  list<string>  $roles */
    public static function highestPriorityRole(array $roles): ?string
    {
        foreach (self::ROLE_PRIORITY as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return $roles[0] ?? null;
    }

    /**
     * Active role rows, ignoring schools that have been soft-deleted.
     */
    private function activeRoleRows(): Builder
    {
        return DB::table('school_user')
            ->leftJoin('schools', 'schools.id', '=', 'school_user.school_id')
            ->where('school_user.user_id', $this->id)
            ->where('school_user.is_active', true)
            ->where(fn (Builder $q) => $q
                ->whereNull('school_user.school_id')
                ->orWhereNull('schools.deleted_at'));
    }
}
