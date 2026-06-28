<?php

namespace App\Http\Controllers\Concerns;

use App\Models\School;
use App\Models\Student;
use App\Models\User;

trait AuthorizesSchoolAdmin
{
    protected function schoolForAdmin(User $user, int $schoolId): School
    {
        $school = School::query()->findOrFail($schoolId);

        if ($user->hasAnyRole('super_admin')) {
            return $school;
        }

        if ($user->roleAtSchool($schoolId) !== 'school_admin') {
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
}
