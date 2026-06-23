<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class OtpAuthController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        try {
            $this->otpService->requestOtp($request->validated('phone'));
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'OTP sent.',
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        try {
            $user = $this->otpService->verifyOtp(
                $request->validated('phone'),
                $request->validated('code'),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    private function userPayload($user): array
    {
        $user->load([
            'schools:id,name,code',
            'children' => fn ($q) => $q->with(['school:id,name', 'schoolClass:id,name', 'section:id,name']),
        ]);

        $studentProfile = Student::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['schoolClass:id,name', 'section:id,name'])
            ->first();

        $teacherSlot = TimetableSlot::query()
            ->where('teacher_id', $user->id)
            ->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'preferred_locale' => $user->preferred_locale,
            'large_text_mode' => $user->large_text_mode,
            'roles' => $user->getRoles(),
            'primary_role' => $user->primaryRole(),
            'student_profile' => $studentProfile ? [
                'id' => $studentProfile->id,
                'name' => $studentProfile->name,
                'school_id' => $studentProfile->school_id,
                'class' => $studentProfile->schoolClass?->name,
                'section' => $studentProfile->section?->name,
            ] : null,
            'teacher_class_id' => $teacherSlot?->school_class_id,
            'teacher_section_id' => $teacherSlot?->section_id,
            'schools' => $user->schools->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'role' => $s->pivot->role,
            ]),
            'children' => $user->children->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'school_id' => $c->school_id,
                'school_name' => $c->school?->name,
                'class' => $c->schoolClass?->name,
                'section' => $c->section?->name,
                'relationship' => $c->pivot->relationship,
            ]),
        ];
    }
}
