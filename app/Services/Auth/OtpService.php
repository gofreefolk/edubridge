<?php

namespace App\Services\Auth;

use App\Models\PhoneOtp;
use App\Models\User;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OtpService
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly SmsService $smsService,
    ) {}

    public function requestOtp(string $rawPhone): void
    {
        $phone = $this->normalizeOrFail($rawPhone);
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
        $phone = $this->normalizeOrFail($rawPhone);
        $maxAttempts = config('edubridge.otp.max_attempts');

        return DB::transaction(function () use ($phone, $code, $maxAttempts) {
            $otp = PhoneOtp::query()
                ->where('phone', $phone)
                ->whereNull('verified_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp || $otp->isExpired()) {
                throw ValidationException::withMessages([
                    'code' => [__('edubridge.otp_expired')],
                ]);
            }

            // Count the attempt before checking the code, conditionally, so concurrent
            // guesses cannot all slip under the limit.
            $claimed = PhoneOtp::query()
                ->whereKey($otp->id)
                ->where('attempts', '<', $maxAttempts)
                ->increment('attempts');

            if ($claimed === 0) {
                throw ValidationException::withMessages([
                    'code' => [__('edubridge.otp_max_attempts')],
                ]);
            }

            if (! Hash::check($code, $otp->code_hash)) {
                // Commit the incremented attempt count; a thrown exception would roll it back.
                return null;
            }

            $otp->update(['verified_at' => now()]);

            return User::query()->firstOrCreate(
                ['phone' => $phone],
                ['name' => __('edubridge.default_parent_name')],
            );
        }) ?? throw ValidationException::withMessages([
            'code' => [__('edubridge.otp_invalid')],
        ]);
    }

    private function normalizeOrFail(string $rawPhone): string
    {
        try {
            return $this->phoneNormalizer->normalize($rawPhone);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'phone' => [__('edubridge.invalid_phone')],
            ]);
        }
    }

    private function generateCode(): string
    {
        $length = config('edubridge.otp.length');
        $devCode = config('edubridge.otp.dev_code');

        if ($devCode && app()->environment('local', 'testing')) {
            return str_pad((string) $devCode, $length, '0', STR_PAD_LEFT);
        }

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
