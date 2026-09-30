<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    use AuthorizesSchoolAdmin;

    public function __construct(
        private readonly AdminDashboardService $dashboard,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $school = $this->schoolForAdmin($request->user(), (int) $data['school_id']);

        return response()->json(
            $this->dashboard->summary($school, isset($data['date']) ? Carbon::parse($data['date']) : null)
        );
    }
}
