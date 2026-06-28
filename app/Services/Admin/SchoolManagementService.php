<?php

namespace App\Services\Admin;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolAdminInvite;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\SmcMember;
use App\Models\Student;
use App\Models\User;
use App\Services\Auth\PhoneNormalizer;
use App\Services\Platform\SchoolOnboardingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SchoolManagementService
{
    public const STAFF_ROLES = ['teacher', 'transport_staff', 'smc_member'];

    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly SchoolOnboardingService $onboarding,
    ) {}

    public function getSchoolProfile(School $school): array
    {
        return [
            'id' => $school->id,
            'name' => $school->name,
            'code' => $school->code,
            'district' => $school->district,
            'type' => $school->type,
            'whatsapp_bridge_enabled' => $school->whatsapp_bridge_enabled,
        ];
    }

    /**
     * @param  array{name?: string, code?: string, district?: string|null, whatsapp_bridge_enabled?: bool}  $data
     */
    public function updateSchoolProfile(School $school, array $data): School
    {
        $school->update([
            'name' => $data['name'] ?? $school->name,
            'code' => isset($data['code']) ? strtoupper($data['code']) : $school->code,
            'district' => array_key_exists('district', $data) ? $data['district'] : $school->district,
            'whatsapp_bridge_enabled' => $data['whatsapp_bridge_enabled'] ?? $school->whatsapp_bridge_enabled,
        ]);

        return $school->fresh();
    }

    public function listInvites(School $school): Collection
    {
        return SchoolAdminInvite::query()
            ->where('school_id', $school->id)
            ->latest('id')
            ->limit(20)
            ->get();
    }

    public function createCoAdminInvite(School $school, string $name, string $phone, User $invitedBy): SchoolAdminInvite
    {
        return $this->onboarding->createInvite($school, $name, $phone, $invitedBy);
    }

    public function currentAcademicYear(School $school): AcademicYear
    {
        $year = AcademicYear::query()
            ->where('school_id', $school->id)
            ->where('is_current', true)
            ->first();

        if ($year) {
            return $year;
        }

        $calendarYear = now()->year;
        $name = now()->month >= 6
            ? $calendarYear.'-'.substr((string) ($calendarYear + 1), -2)
            : ($calendarYear - 1).'-'.substr((string) $calendarYear, -2);

        return AcademicYear::query()->create([
            'school_id' => $school->id,
            'name' => $name,
            'starts_on' => now()->month >= 6
                ? now()->setMonth(6)->setDay(1)->toDateString()
                : now()->subYear()->setMonth(6)->setDay(1)->toDateString(),
            'ends_on' => now()->month >= 6
                ? now()->addYear()->setMonth(3)->setDay(31)->toDateString()
                : now()->setMonth(3)->setDay(31)->toDateString(),
            'is_current' => true,
        ]);
    }

    public function createClass(School $school, string $name, ?int $sortOrder = null): SchoolClass
    {
        $name = trim($name);
        $academicYear = $this->currentAcademicYear($school);

        if ($this->classNameExists($academicYear->id, $name)) {
            throw new InvalidArgumentException('class_name_duplicate:'.$name);
        }

        $maxOrder = SchoolClass::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $academicYear->id)
            ->max('sort_order');

        return SchoolClass::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'name' => $name,
            'sort_order' => $sortOrder ?? (($maxOrder ?? 0) + 1),
        ]);
    }

    public function updateClass(SchoolClass $schoolClass, School $school, string $name, ?int $sortOrder = null): SchoolClass
    {
        $this->ensureClassBelongsToSchool($schoolClass, $school);

        $name = trim($name);

        if ($this->classNameExists($schoolClass->academic_year_id, $name, $schoolClass->id)) {
            throw new InvalidArgumentException('class_name_duplicate:'.$name);
        }

        $schoolClass->update([
            'name' => $name,
            'sort_order' => $sortOrder ?? $schoolClass->sort_order,
        ]);

        return $schoolClass->fresh();
    }

    public function deleteClass(SchoolClass $schoolClass, School $school): void
    {
        $this->ensureClassBelongsToSchool($schoolClass, $school);

        if (Student::query()->where('school_class_id', $schoolClass->id)->exists()) {
            throw new InvalidArgumentException('class_has_students');
        }

        $schoolClass->sections()->delete();
        $schoolClass->delete();
    }

    public function createSection(SchoolClass $schoolClass, School $school, string $name): Section
    {
        $this->ensureClassBelongsToSchool($schoolClass, $school);

        $name = trim($name);

        if ($this->sectionNameExists($schoolClass->id, $name)) {
            throw new InvalidArgumentException('section_name_duplicate:'.$name);
        }

        return Section::query()->create([
            'school_class_id' => $schoolClass->id,
            'name' => $name,
        ]);
    }

    public function updateSection(Section $section, School $school, string $name): Section
    {
        $this->ensureSectionBelongsToSchool($section, $school);

        $name = trim($name);

        if ($this->sectionNameExists($section->school_class_id, $name, $section->id)) {
            throw new InvalidArgumentException('section_name_duplicate:'.$name);
        }

        $section->update(['name' => $name]);

        return $section->fresh();
    }

    public function deleteSection(Section $section, School $school): void
    {
        $this->ensureSectionBelongsToSchool($section, $school);

        if (Student::query()->where('section_id', $section->id)->exists()) {
            throw new InvalidArgumentException('section_has_students');
        }

        $section->delete();
    }

    public function listStaff(School $school): Collection
    {
        return DB::table('school_user')
            ->join('users', 'users.id', '=', 'school_user.user_id')
            ->where('school_user.school_id', $school->id)
            ->whereIn('school_user.role', self::STAFF_ROLES)
            ->where('school_user.is_active', true)
            ->orderBy('school_user.role')
            ->orderBy('users.name')
            ->get([
                'users.id as user_id',
                'users.name',
                'users.phone',
                'school_user.role',
            ]);
    }

    /**
     * @param  array{phone: string, name: string, role: string, smc_role?: string|null}  $data
     */
    public function assignStaff(School $school, array $data): User
    {
        $role = $data['role'];
        if (! in_array($role, self::STAFF_ROLES, true)) {
            throw new InvalidArgumentException('invalid_staff_role');
        }

        $phone = $this->phoneNormalizer->normalize($data['phone']);

        return DB::transaction(function () use ($school, $data, $role, $phone) {
            $user = User::query()->firstOrCreate(
                ['phone' => $phone],
                ['name' => $data['name'], 'preferred_locale' => 'ml'],
            );

            if ($user->name !== $data['name']) {
                $user->update(['name' => $data['name']]);
            }

            $user->schools()->syncWithoutDetaching([
                $school->id => ['role' => $role, 'is_active' => true],
            ]);

            if ($role === 'smc_member') {
                SmcMember::query()->updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'name' => $data['name'],
                        'phone' => $phone,
                        'role' => $data['smc_role'] ?? 'other',
                        'is_active' => true,
                    ],
                );
            }

            return $user->fresh();
        });
    }

    public function removeStaff(School $school, User $user, string $role): void
    {
        if (! in_array($role, self::STAFF_ROLES, true)) {
            throw new InvalidArgumentException('invalid_staff_role');
        }

        DB::table('school_user')
            ->where('school_id', $school->id)
            ->where('user_id', $user->id)
            ->where('role', $role)
            ->update(['is_active' => false, 'updated_at' => now()]);

        if ($role === 'smc_member') {
            SmcMember::query()
                ->where('school_id', $school->id)
                ->where('user_id', $user->id)
                ->update(['is_active' => false]);
        }
    }

    public function listStudents(School $school, array $filters): LengthAwarePaginator
    {
        $query = Student::query()
            ->where('school_id', $school->id)
            ->with([
                'schoolClass:id,name',
                'section:id,name',
                'parents:id,name,phone',
            ]);

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('admission_number', 'like', $term);
            });
        }

        if (! empty($filters['school_class_id'])) {
            $query->where('school_class_id', $filters['school_class_id']);
        }

        if (! empty($filters['section_id'])) {
            $query->where('section_id', $filters['section_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * @param  array{name?: string, admission_number?: string|null, school_class_id?: int|null, section_id?: int|null, status?: string}  $data
     */
    public function updateStudent(Student $student, School $school, array $data): Student
    {
        if ($student->school_id !== $school->id) {
            throw new InvalidArgumentException('student_not_in_school');
        }

        if (isset($data['school_class_id']) && $data['school_class_id']) {
            $class = SchoolClass::query()->findOrFail($data['school_class_id']);
            $this->ensureClassBelongsToSchool($class, $school);
        }

        if (isset($data['section_id']) && $data['section_id']) {
            $section = Section::query()->findOrFail($data['section_id']);
            $this->ensureSectionBelongsToSchool($section, $school);
        }

        $student->update([
            'name' => $data['name'] ?? $student->name,
            'admission_number' => array_key_exists('admission_number', $data) ? $data['admission_number'] : $student->admission_number,
            'school_class_id' => array_key_exists('school_class_id', $data) ? $data['school_class_id'] : $student->school_class_id,
            'section_id' => array_key_exists('section_id', $data) ? $data['section_id'] : $student->section_id,
            'status' => $data['status'] ?? $student->status,
        ]);

        return $student->fresh(['schoolClass', 'section', 'parents']);
    }

    public function attachParent(Student $student, School $school, array $data): User
    {
        if ($student->school_id !== $school->id) {
            throw new InvalidArgumentException('student_not_in_school');
        }

        $phone = $this->phoneNormalizer->normalize($data['phone']);

        return DB::transaction(function () use ($student, $school, $data, $phone) {
            $parent = User::query()->firstOrCreate(
                ['phone' => $phone],
                ['name' => $data['name'], 'preferred_locale' => 'ml'],
            );

            if ($parent->name !== $data['name']) {
                $parent->update(['name' => $data['name']]);
            }

            $parent->schools()->syncWithoutDetaching([
                $school->id => ['role' => 'parent', 'is_active' => true],
            ]);

            if (! empty($data['is_primary'])) {
                DB::table('parent_student')
                    ->where('student_id', $student->id)
                    ->update(['is_primary' => false]);
            }

            $parent->children()->syncWithoutDetaching([
                $student->id => [
                    'relationship' => $data['relationship'] ?? 'guardian',
                    'is_primary' => (bool) ($data['is_primary'] ?? false),
                ],
            ]);

            return $parent->fresh();
        });
    }

    public function updateParentLink(Student $student, School $school, User $parent, array $data): void
    {
        if ($student->school_id !== $school->id) {
            throw new InvalidArgumentException('student_not_in_school');
        }

        if (! $student->parents()->where('users.id', $parent->id)->exists()) {
            throw new InvalidArgumentException('parent_not_linked');
        }

        if (! empty($data['is_primary'])) {
            DB::table('parent_student')
                ->where('student_id', $student->id)
                ->update(['is_primary' => false]);
        }

        $student->parents()->updateExistingPivot($parent->id, [
            'relationship' => $data['relationship'] ?? 'guardian',
            'is_primary' => (bool) ($data['is_primary'] ?? false),
        ]);
    }

    public function detachParent(Student $student, School $school, User $parent): void
    {
        if ($student->school_id !== $school->id) {
            throw new InvalidArgumentException('student_not_in_school');
        }

        $student->parents()->detach($parent->id);
    }

    private function ensureClassBelongsToSchool(SchoolClass $schoolClass, School $school): void
    {
        if ($schoolClass->school_id !== $school->id) {
            throw new InvalidArgumentException('class_not_in_school');
        }
    }

    private function ensureSectionBelongsToSchool(Section $section, School $school): void
    {
        $section->loadMissing('schoolClass');
        if ($section->schoolClass?->school_id !== $school->id) {
            throw new InvalidArgumentException('section_not_in_school');
        }
    }

    private function classNameExists(int $academicYearId, string $name, ?int $exceptClassId = null): bool
    {
        return SchoolClass::query()
            ->where('academic_year_id', $academicYearId)
            ->where('name', $name)
            ->when($exceptClassId, fn ($q) => $q->where('id', '!=', $exceptClassId))
            ->exists();
    }

    private function sectionNameExists(int $schoolClassId, string $name, ?int $exceptSectionId = null): bool
    {
        return Section::query()
            ->where('school_class_id', $schoolClassId)
            ->where('name', $name)
            ->when($exceptSectionId, fn ($q) => $q->where('id', '!=', $exceptSectionId))
            ->exists();
    }
}
