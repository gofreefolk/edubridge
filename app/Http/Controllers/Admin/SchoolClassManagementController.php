<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\Admin\SchoolManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SchoolClassManagementController extends Controller
{
    use AuthorizesSchoolAdmin;
    use MapsSchoolManagementErrors;

    public function __construct(
        private readonly SchoolManagementService $management,
    ) {}

    public function storeClass(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        try {
            $class = $this->management->createClass($school, $data['name'], $data['sort_order'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->managementError($e);
        }

        return response()->json(['class' => $class], 201);
    }

    public function updateClass(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        try {
            $class = $this->management->updateClass(
                $schoolClass,
                $school,
                $data['name'],
                $data['sort_order'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return $this->managementError($e);
        }

        return response()->json(['class' => $class]);
    }

    public function destroyClass(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        try {
            $this->management->deleteClass($schoolClass, $school);
        } catch (InvalidArgumentException $e) {
            return $this->managementError($e);
        }

        return response()->json(['message' => __('edubridge.class_deleted')]);
    }

    public function storeSection(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:50'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        try {
            $section = $this->management->createSection($schoolClass, $school, $data['name']);
        } catch (InvalidArgumentException $e) {
            return $this->managementError($e);
        }

        return response()->json(['section' => $section], 201);
    }

    public function updateSection(Request $request, Section $section): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:50'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        try {
            $section = $this->management->updateSection($section, $school, $data['name']);
        } catch (InvalidArgumentException $e) {
            return $this->managementError($e);
        }

        return response()->json(['section' => $section]);
    }

    public function destroySection(Request $request, Section $section): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        try {
            $this->management->deleteSection($section, $school);
        } catch (InvalidArgumentException $e) {
            return $this->managementError($e);
        }

        return response()->json(['message' => __('edubridge.section_deleted')]);
    }
}
