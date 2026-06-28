<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\SchoolOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SchoolRegistrationController extends Controller
{
    public function __construct(
        private readonly SchoolOnboardingService $onboarding,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'unique:schools,code'],
            'district' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:government,aided,private'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_phone' => ['required', 'string', 'max:20'],
        ]);

        try {
            $school = $this->onboarding->submitRegistration($data);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => __('edubridge.invalid_phone'),
            ], 422);
        }

        return response()->json([
            'message' => __('edubridge.registration_submitted'),
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->code,
                'approval_status' => $school->approval_status,
            ],
        ], 201);
    }
}
