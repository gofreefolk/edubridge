<?php

namespace App\Http\Controllers\Concerns;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;

/**
 * Per-school authorization. The `role:` route middleware only proves a user holds a
 * role at *some* school; every request touching school data must also pass one of these.
 */
trait AuthorizesSchoolAdmin
{
    protected function schoolForAdmin(User $user, int $schoolId): School
    {
        return $this->schoolForRoles($user, $schoolId, 'school_admin');
    }

    protected function schoolForRoles(User $user, int $schoolId, string ...$roles): School
    {
        $school = School::query()->findOrFail($schoolId);

        if (! $user->hasRoleAtSchool($school->id, ...$roles)) {
            abort(403, __('edubridge.unauthorized_role'));
        }

        return $school;
    }

    protected function ensureStudentBelongsToSchool(Student $student, School $school): void
    {
        if ($student->school_id !== $school->id) {
            abort(404);
        }
    }

    protected function ensureCanAccessStudent(User $user, Student $student): void
    {
        if (! $user->canAccessStudent($student)) {
            abort(403, __('edubridge.forbidden'));
        }
    }

    /**
     * Rejects class / section / subject ids that belong to another school.
     */
    protected function ensureAcademicRefsBelongToSchool(int $schoolId, ?int $classId = null, ?int $sectionId = null, ?int $subjectId = null): void
    {
        if ($classId && ! SchoolClass::query()->whereKey($classId)->where('school_id', $schoolId)->exists()) {
            abort(422, __('edubridge.invalid_reference'));
        }

        if ($sectionId) {
            $section = Section::query()->with('schoolClass:id,school_id')->find($sectionId);

            if (! $section || $section->schoolClass?->school_id !== $schoolId
                || ($classId && $section->school_class_id !== $classId)) {
                abort(422, __('edubridge.invalid_reference'));
            }
        }

        if ($subjectId && ! Subject::query()->whereKey($subjectId)->where('school_id', $schoolId)->exists()) {
            abort(422, __('edubridge.invalid_reference'));
        }
    }
}
