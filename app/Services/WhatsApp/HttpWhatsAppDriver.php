<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpWhatsAppDriver implements WhatsAppDriver
{
    public function send(string $phone, string $message): bool
    {
        $url = config('edubridge.whatsapp.webhook_url');

        if (! $url) {
            Log::warning('WhatsApp webhook URL not configured', ['phone' => $phone]);

            return false;
        }

        $response = Http::timeout(15)->post($url, [
            'phone' => $phone,
            'message' => $message,
        ]);

        if (! $response->successful()) {
            Log::warning('WhatsApp webhook failed', [
                'phone' => $phone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }
}
