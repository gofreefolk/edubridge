<?php

namespace App\Services\Auth;

use App\Models\PhoneOtp;
use App\Models\User;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly SmsService $smsService,
    ) {}

    public function requestOtp(string $rawPhone): void
    {
        $phone = $this->phoneNormalizer->normalize($rawPhone);
        $cooldown = config('edubridge.otp.resend_cooldown_seconds');

        $recent = PhoneOtp::query()
            ->where('phone', $phone)
            ->whereNull('verified_at')
            ->where('created_at', '>=', now()->subSeconds($cooldown))
            ->latest('id')
            ->first();

        if ($recent) {
            throw ValidationException::withMessages([
                'phone' => [__('edubridge.otp_cooldown', ['seconds' => $cooldown])],
            ]);
        }

        $code = $this->generateCode();
        $expiresAt = now()->addMinutes(config('edubridge.otp.expiry_minutes'));

        PhoneOtp::query()
            ->where('phone', $phone)
            ->whereNull('verified_at')
            ->delete();

        PhoneOtp::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => $expiresAt,
        ]);

        $this->smsService->sendOtp(
            $this->phoneNormalizer->toE164($phone),
            $code,
        );
    }

    public function verifyOtp(string $rawPhone, string $code): User
    {
        $phone = $this->phoneNormalizer->normalize($rawPhone);
        $otp = PhoneOtp::query()
            ->where('phone', $phone)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $otp || $otp->isExpired()) {
            throw ValidationException::withMessages([
                'code' => [__('edubridge.otp_expired')],
            ]);
        }

        if ($otp->attempts >= config('edubridge.otp.max_attempts')) {
            throw ValidationException::withMessages([
                'code' => [__('edubridge.otp_max_attempts')],
            ]);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            throw ValidationException::withMessages([
                'code' => [__('edubridge.otp_invalid')],
            ]);
        }

        return DB::transaction(function () use ($otp, $phone) {
            $otp->update(['verified_at' => now()]);

            return User::query()->firstOrCreate(
                ['phone' => $phone],
                ['name' => __('edubridge.default_parent_name')],
            );
        });
    }

    private function generateCode(): string
    {
        $length = config('edubridge.otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
