<?php

namespace App\Providers;

use App\Services\Sms\LogSmsDriver;
use App\Services\Sms\SmsService;
use App\Services\WhatsApp\HttpWhatsAppDriver;
use App\Services\WhatsApp\LogWhatsAppDriver;
use App\Services\WhatsApp\WhatsAppDriver;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\ServiceProvider;

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

        $this->app->singleton(SmsService::class, function ($app) {
            return new SmsService($app->make(LogSmsDriver::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
