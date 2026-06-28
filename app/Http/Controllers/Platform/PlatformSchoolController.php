<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolAdminInvite;
use App\Services\Platform\SchoolOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PlatformSchoolController extends Controller
{
    public function __construct(
        private readonly SchoolOnboardingService $onboarding,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'unique:schools,code'],
            'district' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:government,aided,private'],
            'whatsapp_bridge_enabled' => ['sometimes', 'boolean'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_phone' => ['required', 'string', 'max:20'],
        ]);

        try {
            $result = $this->onboarding->createApprovedSchool($data, $request->user());
        } catch (InvalidArgumentException $e) {
            return $this->onboardingError($e);
        }

        return response()->json([
            'message' => __('edubridge.school_created'),
            'school' => $this->schoolPayload($result['school']),
            'invite' => $this->invitePayload($result['invite']),
        ], 201);
    }

    public function show(Request $request, School $school): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $school->loadCount(['students', 'users', 'notices']);
        $invites = $school->adminInvites()
            ->with('acceptedBy:id,name,phone')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'school' => $this->schoolPayload($school),
            'invites' => $invites->map(fn (SchoolAdminInvite $invite) => $this->invitePayload($invite)),
        ]);
    }

    public function registrations(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $schools = School::query()
            ->where('approval_status', School::APPROVAL_PENDING)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'registrations' => $schools->map(fn (School $school) => $this->schoolPayload($school)),
        ]);
    }

    public function approve(Request $request, School $school): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        try {
            $result = $this->onboarding->approveSchool($school, $request->user());
        } catch (InvalidArgumentException $e) {
            return $this->onboardingError($e);
        }

        return response()->json([
            'message' => __('edubridge.school_approved'),
            'school' => $this->schoolPayload($result['school']),
            'invite' => $result['invite'] ? $this->invitePayload($result['invite']) : null,
        ]);
    }

    public function reject(Request $request, School $school): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $school = $this->onboarding->rejectSchool($school, $request->user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            return $this->onboardingError($e);
        }

        return response()->json([
            'message' => __('edubridge.school_rejected'),
            'school' => $this->schoolPayload($school),
        ]);
    }

    public function storeInvite(Request $request, School $school): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_phone' => ['required', 'string', 'max:20'],
        ]);

        try {
            $invite = $this->onboarding->createInvite(
                $school,
                $data['admin_name'],
                $data['admin_phone'],
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->onboardingError($e);
        }

        return response()->json([
            'message' => __('edubridge.invite_sent'),
            'invite' => $this->invitePayload($invite),
        ], 201);
    }

    private function schoolPayload(School $school): array
    {
        return [
            'id' => $school->id,
            'name' => $school->name,
            'code' => $school->code,
            'district' => $school->district,
            'type' => $school->type,
            'is_active' => $school->is_active,
            'approval_status' => $school->approval_status,
            'admin_contact_name' => $school->admin_contact_name,
            'admin_contact_phone' => $school->admin_contact_phone,
            'rejection_reason' => $school->rejection_reason,
            'whatsapp_bridge_enabled' => $school->whatsapp_bridge_enabled,
            'students_count' => $school->students_count ?? 0,
            'users_count' => $school->users_count ?? 0,
            'notices_count' => $school->notices_count ?? 0,
            'reviewed_at' => $school->reviewed_at?->toIso8601String(),
            'created_at' => $school->created_at?->toIso8601String(),
        ];
    }

    private function invitePayload(SchoolAdminInvite $invite): array
    {
        return [
            'id' => $invite->id,
            'school_id' => $invite->school_id,
            'phone' => $invite->phone,
            'name' => $invite->name,
            'status' => $invite->status,
            'invite_url' => $this->onboarding->inviteUrl($invite),
            'expires_at' => $invite->expires_at?->toIso8601String(),
            'accepted_at' => $invite->accepted_at?->toIso8601String(),
            'accepted_by' => $invite->acceptedBy ? [
                'id' => $invite->acceptedBy->id,
                'name' => $invite->acceptedBy->name,
                'phone' => $invite->acceptedBy->phone,
            ] : null,
        ];
    }

    private function onboardingError(InvalidArgumentException $e): JsonResponse
    {
        $key = match ($e->getMessage()) {
            'school_not_pending' => 'edubridge.school_not_pending',
            'school_not_approved' => 'edubridge.school_not_approved',
            default => 'edubridge.generic_error',
        };

        return response()->json(['message' => __($key)], 422);
    }

    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Platform admin access required.');
    }
}
