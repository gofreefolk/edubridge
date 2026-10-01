<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Controller;
use App\Models\FeeGatewayAccount;
use App\Models\FeePaymentLink;
use App\Models\School;
use App\Services\Fees\OnlineFeePaymentService;
use App\Services\Fees\RazorpayClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

/**
 * Phase 2b online fee payment: the school's Razorpay keys, the signed browser return
 * from the payment page, and the Razorpay webhook.
 */
class FeeOnlinePaymentController extends Controller
{
    use AuthorizesSchoolAdmin;

    public function __construct(
        private readonly OnlineFeePaymentService $online,
        private readonly RazorpayClient $razorpay,
    ) {}

    public function gateway(Request $request): JsonResponse
    {
        $data = $request->validate(['school_id' => ['required', 'integer', 'exists:schools,id']]);
        $school = $this->schoolForAdmin($request->user(), (int) $data['school_id']);

        return response()->json(['gateway' => $this->gatewayPayload($school)]);
    }

    public function updateGateway(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'key_id' => ['required', 'string', 'max:100', 'regex:/^rzp_(live|test)_[A-Za-z0-9]+$/'],
            'key_secret' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
        ]);

        $school = $this->schoolForAdmin($request->user(), (int) $data['school_id']);
        $account = FeeGatewayAccount::query()->firstOrNew(['school_id' => $school->id]);

        // Secrets are write-only: blank keeps the saved one, unless the key id changed.
        if (empty($data['key_secret']) && (! $account->exists || $account->key_id !== $data['key_id'])) {
            return $this->error('fee_gateway_secret_required');
        }

        $account->fill([
            'provider' => 'razorpay',
            'key_id' => $data['key_id'],
            'is_enabled' => $data['is_enabled'],
            'updated_by' => $request->user()->id,
        ]);

        if (! empty($data['key_secret'])) {
            $account->key_secret = $data['key_secret'];
        }

        if (! empty($data['webhook_secret'])) {
            $account->webhook_secret = $data['webhook_secret'];
        }

        if ($account->is_enabled) {
            if (! $account->webhook_secret) {
                return $this->error('fee_gateway_webhook_required');
            }

            try {
                if (! $this->razorpay->credentialsWork($account)) {
                    return $this->error('fee_gateway_keys_invalid');
                }
            } catch (RuntimeException $e) {
                return $this->error($e->getMessage());
            }
        }

        $account->save();

        return response()->json(['gateway' => $this->gatewayPayload($school)]);
    }

    /**
     * The payment page redirects the parent's browser back with signed query params;
     * the SPA posts them here. No login needed: the signature is the proof.
     */
    public function confirm(Request $request): JsonResponse
    {
        $params = $request->validate([
            'razorpay_payment_id' => ['nullable', 'string', 'max:100'],
            'razorpay_payment_link_id' => ['required', 'string', 'max:100'],
            'razorpay_payment_link_reference_id' => ['nullable', 'string', 'max:100'],
            'razorpay_payment_link_status' => ['required', 'string', 'max:30'],
            'razorpay_signature' => ['required', 'string', 'max:200'],
        ]);

        try {
            $payment = $this->online->confirmCallback($params);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        }

        $link = FeePaymentLink::query()->where('gateway_link_id', $params['razorpay_payment_link_id'])->first();

        return response()->json([
            'paid' => (bool) $payment,
            'payment_id' => $payment?->id,
            'receipt_number' => $payment?->receipt_number,
            'student_id' => $link?->invoice?->student_id,
        ]);
    }

    public function webhook(Request $request, School $school): JsonResponse
    {
        $ok = $this->online->handleWebhook($school, $request->getContent(), $request->header('X-Razorpay-Signature'));

        return response()->json(['ok' => $ok], $ok ? 200 : 400);
    }

    private function gatewayPayload(School $school): array
    {
        $account = FeeGatewayAccount::query()->where('school_id', $school->id)->first();

        return [
            'provider' => 'razorpay',
            'configured' => (bool) $account,
            'key_id' => $account?->key_id,
            'has_key_secret' => (bool) $account?->key_secret,
            'has_webhook_secret' => (bool) $account?->webhook_secret,
            'is_enabled' => (bool) $account?->is_enabled,
            'webhook_url' => url("/api/webhooks/razorpay/{$school->id}"),
            'webhook_events' => ['payment_link.paid', 'payment_link.cancelled', 'payment_link.expired'],
        ];
    }

    private function error(string $key): JsonResponse
    {
        return response()->json(['message' => __('edubridge.'.$key)], 422);
    }
}
