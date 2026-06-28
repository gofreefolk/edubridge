<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\SchoolAdminInvite;
use App\Services\Platform\SchoolOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SchoolAdminInviteController extends Controller
{
    public function __construct(
        private readonly SchoolOnboardingService $onboarding,
    ) {}

    public function show(string $token): JsonResponse
    {
        $invite = SchoolAdminInvite::query()
            ->with('school:id,name,code,district,type,approval_status')
            ->where('token', $token)
            ->first();

        if (! $invite) {
            return response()->json(['message' => __('edubridge.invite_invalid')], 404);
        }

        if ($invite->status === SchoolAdminInvite::STATUS_PENDING && $invite->expires_at->isPast()) {
            $invite->update(['status' => SchoolAdminInvite::STATUS_EXPIRED]);
            $invite->refresh();
        }

        return response()->json([
            'invite' => [
                'token' => $invite->token,
                'phone' => $invite->phone,
                'name' => $invite->name,
                'status' => $invite->status,
                'expires_at' => $invite->expires_at?->toIso8601String(),
                'school' => [
                    'name' => $invite->school->name,
                    'code' => $invite->school->code,
                    'district' => $invite->school->district,
                    'type' => $invite->school->type,
                ],
            ],
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        try {
            $invite = $this->onboarding->acceptInvite($token, $user);
        } catch (InvalidArgumentException $e) {
            $key = match ($e->getMessage()) {
                'invite_expired' => 'edubridge.invite_expired',
                'invite_phone_mismatch' => 'edubridge.invite_phone_mismatch',
                default => 'edubridge.invite_invalid',
            };

            return response()->json(['message' => __($key)], 422);
        }

        return response()->json([
            'message' => __('edubridge.invite_accepted'),
            'school' => [
                'id' => $invite->school_id,
                'name' => $invite->school->name,
            ],
        ]);
    }
}
