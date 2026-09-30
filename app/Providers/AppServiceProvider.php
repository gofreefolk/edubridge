<?php

namespace App\Providers;

use App\Services\Sms\HttpSmsDriver;
use App\Services\Sms\LogSmsDriver;
use App\Services\Sms\SmsDriver;
use App\Services\WhatsApp\HttpWhatsAppDriver;
use App\Services\WhatsApp\LogWhatsAppDriver;
use App\Services\WhatsApp\WhatsAppDriver;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WhatsAppDriver::class, function () {
            return match (config('edubridge.whatsapp.driver')) {
                'http' => new HttpWhatsAppDriver,
                default => new LogWhatsAppDriver,
            };
        });

        $this->app->singleton(WhatsAppService::class, function ($app) {
            return new WhatsAppService($app->make(WhatsAppDriver::class));
        });

        $this->app->bind(SmsDriver::class, function () {
            return match (config('edubridge.sms.driver')) {
                'http' => new HttpSmsDriver,
                'log' => new LogSmsDriver,
                default => throw new InvalidArgumentException('Unsupported SMS driver: '.config('edubridge.sms.driver')),
            };
        });
    }

    public function boot(): void
    {
        $tooMany = fn () => response()->json(['message' => __('edubridge.too_many_requests')], 429);

        // Per IP and per phone, so one attacker cannot spray SMS across many numbers
        // or brute-force one number's code from many connections.
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(10)->by('otp-ip:'.$request->ip())->response($tooMany),
            Limit::perMinute(5)->by('otp-phone:'.preg_replace('/\D+/', '', (string) $request->input('phone')))->response($tooMany),
            Limit::perHour(30)->by('otp-phone-hour:'.preg_replace('/\D+/', '', (string) $request->input('phone')))->response($tooMany),
        ]);

        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(5)->by($request->ip())->response($tooMany));
    }
}
