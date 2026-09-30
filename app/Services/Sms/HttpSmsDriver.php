<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Posts {phone, message} as JSON to an SMS gateway webhook (e.g. a small adapter in
 * front of MSG91 / Twilio / Fast2SMS). An optional bearer token is sent when configured.
 */
class HttpSmsDriver implements SmsDriver
{
    public function send(string $phone, string $message): void
    {
        $url = config('edubridge.sms.webhook_url');

        if (! $url) {
            throw new RuntimeException('SMS webhook URL is not configured (EDUBRIDGE_SMS_WEBHOOK_URL).');
        }

        $request = Http::timeout(15);

        if ($token = config('edubridge.sms.webhook_token')) {
            $request = $request->withToken($token);
        }

        $response = $request->post($url, [
            'phone' => $phone,
            'message' => $message,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('SMS gateway returned HTTP '.$response->status());
        }
    }
}
