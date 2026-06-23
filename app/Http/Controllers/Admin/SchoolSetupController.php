<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClass;
use App\Services\Admin\ParentStudentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchoolSetupController extends Controller
{
    public function classes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
        ]);

        $classes = SchoolClass::query()
            ->where('school_id', $data['school_id'])
            ->with(['sections:id,school_class_id,name'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'school_id', 'name']);

        return response()->json(['classes' => $classes]);
    }

    public function downloadSample(): BinaryFileResponse
    {
        $path = public_path('samples/edubridge-parent-student-import.csv');

        return response()->download($path, 'edubridge-parent-student-import.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function importParents(Request $request, ParentStudentImportService $importService): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $school = School::query()->findOrFail($data['school_id']);

        try {
            $result = $importService->import($school, $request->file('file'));
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => __('edubridge.import_complete', [
                'imported' => $result['imported'],
                'failed' => $result['failed'],
            ]),
            ...$result,
        ]);
    }
}
