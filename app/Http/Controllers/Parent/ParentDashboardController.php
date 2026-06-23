<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Services\Parent\ParentDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentDashboardController extends Controller
{
    public function __construct(
        private readonly ParentDashboardService $dashboardService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'student_id' => ['nullable', 'exists:students,id'],
        ]);

        return response()->json(
            $this->dashboardService->dashboard(
                $request->user(),
                (int) $request->query('school_id'),
                $request->query('student_id') ? (int) $request->query('student_id') : null,
            ),
        );
    }
}
