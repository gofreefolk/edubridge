<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Services\Admin\SchoolManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class SchoolStudentController extends Controller
{
    use AuthorizesSchoolAdmin;

    public function __construct(
        private readonly SchoolManagementService $management,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'school_class_id' => ['sometimes', 'nullable', 'integer'],
            'section_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'graduated', 'alumni', 'transferred'])],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);
        $paginator = $this->management->listStudents($school, $data);

        return response()->json([
            'students' => $paginator->through(fn (Student $student) => $this->studentPayload($student)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);
        $this->ensureStudentBelongsToSchool($student, $school);

        $student->load(['schoolClass', 'section', 'parents']);

        return response()->json([
            'student' => $this->studentPayload($student),
        ]);
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'admission_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'school_class_id' => ['sometimes', 'nullable', 'integer'],
            'section_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', Rule::in(['active', 'graduated', 'alumni', 'transferred'])],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        try {
            $student = $this->management->updateStudent($student, $school, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'student' => $this->studentPayload($student),
        ]);
    }

    public function attachParent(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'relationship' => ['sometimes', Rule::in(['father', 'mother', 'guardian', 'grandparent', 'other'])],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        try {
            $parent = $this->management->attachParent($student, $school, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'parent' => [
                'id' => $parent->id,
                'name' => $parent->name,
                'phone' => $parent->phone,
            ],
        ], 201);
    }

    public function updateParent(Request $request, Student $student, User $parent): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'relationship' => ['sometimes', Rule::in(['father', 'mother', 'guardian', 'grandparent', 'other'])],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        try {
            $this->management->updateParentLink($student, $school, $parent, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $student->load('parents');

        return response()->json([
            'student' => $this->studentPayload($student),
        ]);
    }

    public function detachParent(Request $request, Student $student, User $parent): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        try {
            $this->management->detachParent($student, $school, $parent);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Detached.']);
    }

    private function studentPayload(Student $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'admission_number' => $student->admission_number,
            'status' => $student->status,
            'school_class_id' => $student->school_class_id,
            'section_id' => $student->section_id,
            'class' => $student->schoolClass?->name,
            'section' => $student->section?->name,
            'parents' => $student->parents?->map(fn (User $parent) => [
                'id' => $parent->id,
                'name' => $parent->name,
                'phone' => $parent->phone,
                'relationship' => $parent->pivot->relationship,
                'is_primary' => (bool) $parent->pivot->is_primary,
            ]) ?? [],
        ];
    }
}
