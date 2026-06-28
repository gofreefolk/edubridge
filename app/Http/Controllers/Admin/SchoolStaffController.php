<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\SchoolManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class SchoolStaffController extends Controller
{
    use AuthorizesSchoolAdmin;

    public function __construct(
        private readonly SchoolManagementService $management,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        return response()->json([
            'staff' => $this->management->listStaff($school),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'role' => ['required', Rule::in(SchoolManagementService::STAFF_ROLES)],
            'smc_role' => ['sometimes', 'nullable', Rule::in(['parent_rep', 'teacher_rep', 'lsg_rep', 'headmaster', 'other'])],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        try {
            $user = $this->management->assignStaff($school, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'role' => $data['role'],
            ],
        ], 201);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'role' => ['required', Rule::in(SchoolManagementService::STAFF_ROLES)],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        try {
            $this->management->removeStaff($school, $user, $data['role']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Removed.']);
    }
}
