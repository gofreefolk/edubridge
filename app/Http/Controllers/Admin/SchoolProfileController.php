<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Services\Admin\SchoolManagementService;
use App\Services\Platform\SchoolOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class SchoolProfileController extends Controller
{
    use AuthorizesSchoolAdmin;

    public function __construct(
        private readonly SchoolManagementService $management,
        private readonly SchoolOnboardingService $onboarding,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        return response()->json([
            'school' => $this->management->getSchoolProfile($school),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50'],
            'district' => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp_bridge_enabled' => ['sometimes', 'boolean'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        if (isset($data['code'])) {
            $request->validate([
                'code' => [Rule::unique('schools', 'code')->ignore($school->id)],
            ]);
        }

        $school = $this->management->updateSchoolProfile($school, $data);

        return response()->json([
            'school' => $this->management->getSchoolProfile($school),
        ]);
    }

    public function invites(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);
        $invites = $this->management->listInvites($school);

        return response()->json([
            'invites' => $invites->map(fn ($invite) => [
                'id' => $invite->id,
                'name' => $invite->name,
                'phone' => $invite->phone,
                'status' => $invite->status,
                'expires_at' => $invite->expires_at?->toIso8601String(),
                'invite_url' => $invite->status === 'pending'
                    ? $this->onboarding->inviteUrl($invite)
                    : null,
            ]),
        ]);
    }

    public function storeInvite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $school = $this->schoolForAdmin($request->user(), $data['school_id']);

        try {
            $invite = $this->management->createCoAdminInvite(
                $school,
                $data['name'],
                $data['phone'],
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'invite' => [
                'id' => $invite->id,
                'name' => $invite->name,
                'phone' => $invite->phone,
                'status' => $invite->status,
                'invite_url' => $this->onboarding->inviteUrl($invite),
            ],
        ], 201);
    }
}
