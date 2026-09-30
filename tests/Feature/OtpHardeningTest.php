<?php

namespace Tests\Feature;

use App\Models\PhoneOtp;
use App\Services\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OtpHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_phone_returns_validation_error_not_500(): void
    {
        $this->postJson('/api/auth/otp/request', ['phone' => '12345678901234'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    public function test_wrong_codes_are_counted_and_then_locked(): void
    {
        PhoneOtp::create([
            'phone' => '9123456789',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/otp/verify', ['phone' => '9123456789', 'code' => '000000'])
                ->assertStatus(422);
        }

        $this->assertSame(5, PhoneOtp::query()->first()->attempts);

        // Further HTTP attempts are throttled...
        $this->postJson('/api/auth/otp/verify', ['phone' => '9123456789', 'code' => '123456'])
            ->assertStatus(429);

        // ...and even past the throttle, the right code is refused once the budget is spent.
        try {
            app(OtpService::class)->verifyOtp('9123456789', '123456');
            $this->fail('Expected the OTP to be locked.');
        } catch (ValidationException $e) {
            $this->assertSame(__('edubridge.otp_max_attempts'), $e->errors()['code'][0]);
        }
    }

    public function test_otp_requests_are_rate_limited_per_phone(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/otp/request', ['phone' => '9123456789']);
        }

        $this->postJson('/api/auth/otp/request', ['phone' => '9123456789'])
            ->assertStatus(429);
    }
}
