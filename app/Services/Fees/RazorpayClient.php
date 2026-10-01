<?php

namespace App\Services\Fees;

use App\Models\FeeGatewayAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over the Razorpay REST API (payment links) using each school's own keys.
 * Throws RuntimeException('fee_gateway_error') on any API failure so callers can show
 * one message; the response body is logged by the HTTP client exception.
 */
class RazorpayClient
{
    private const BASE_URL = 'https://api.razorpay.com/v1';

    /**
     * @return array{id: string, short_url: string, status: string, expire_by: ?int}
     */
    public function createPaymentLink(FeeGatewayAccount $account, array $payload): array
    {
        return $this->send(fn () => $this->http($account)->post('/payment_links', $payload)->throw()->json());
    }

    public function cancelPaymentLink(FeeGatewayAccount $account, string $linkId): void
    {
        $this->send(fn () => $this->http($account)->post("/payment_links/{$linkId}/cancel")->throw());
    }

    /**
     * True when the keys authenticate; false on 401. Other failures throw.
     */
    public function credentialsWork(FeeGatewayAccount $account): bool
    {
        $response = $this->send(fn () => $this->http($account)->get('/payments', ['count' => 1]));

        if ($response->status() === 401) {
            return false;
        }

        $this->send(fn () => $response->throw());

        return true;
    }

    public static function webhookSignatureValid(string $body, ?string $signature, string $secret): bool
    {
        return $signature !== null && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    /**
     * Signature Razorpay appends to the payment link callback redirect.
     */
    public static function callbackSignatureValid(array $params, string $keySecret): bool
    {
        $payload = implode('|', [
            $params['razorpay_payment_link_id'] ?? '',
            $params['razorpay_payment_link_reference_id'] ?? '',
            $params['razorpay_payment_link_status'] ?? '',
            $params['razorpay_payment_id'] ?? '',
        ]);

        return isset($params['razorpay_signature'])
            && hash_equals(hash_hmac('sha256', $payload, $keySecret), (string) $params['razorpay_signature']);
    }

    private function http(FeeGatewayAccount $account): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withBasicAuth($account->key_id, $account->key_secret)
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }

    private function send(callable $call): mixed
    {
        try {
            return $call();
        } catch (RequestException|ConnectionException $e) {
            report($e);

            throw new RuntimeException('fee_gateway_error', previous: $e);
        }
    }
}
