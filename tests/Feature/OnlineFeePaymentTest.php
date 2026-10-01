<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\FeeGatewayAccount;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeePaymentLink;
use App\Models\NotificationLog;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnlineFeePaymentTest extends TestCase
{
    use RefreshDatabase;

    private const KEY_SECRET = 'test_key_secret';

    private const WEBHOOK_SECRET = 'test_webhook_secret';

    private School $school;

    private User $admin;

    private User $parent;

    private Student $child;

    private int $linkCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PilotSchoolSeeder::class);
        $this->school = School::query()->where('code', 'STMS-LP')->firstOrFail();
        $this->admin = User::query()->where('phone', '9876543210')->firstOrFail();
        $this->parent = User::query()->where('phone', '9123456789')->firstOrFail();
        $this->child = $this->parent->children()->firstOrFail();

        $this->fakeRazorpay();
    }

    /** A fresh fake each time: Http::fake() otherwise appends, and the first match wins. */
    private function fakeRazorpay(array $overrides = []): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            'api.razorpay.com/v1/payments*' => Http::response(['items' => []]),
            'api.razorpay.com/v1/payment_links/*/cancel' => Http::response(['status' => 'cancelled']),
            'api.razorpay.com/v1/payment_links' => function () {
                $this->linkCounter++;

                return Http::response([
                    'id' => "plink_test{$this->linkCounter}",
                    'short_url' => "https://rzp.io/i/test{$this->linkCounter}",
                    'status' => 'created',
                ]);
            },
            ...$overrides,
        ]);
    }

    /** ₹1,000 tuition for every class, due in a week. */
    private function invoice(): FeeInvoice
    {
        $year = AcademicYear::query()->where('school_id', $this->school->id)->firstOrFail();
        $head = $this->actingAs($this->admin)
            ->postJson('/api/fees/heads', ['school_id' => $this->school->id, 'name' => 'Tuition'])
            ->json('head.id');

        $this->actingAs($this->admin)->postJson('/api/fees/structures', [
            'school_id' => $this->school->id,
            'academic_year_id' => $year->id,
            'fee_head_id' => $head,
            'label' => 'Term 1',
            'amount_paise' => 100000,
            'due_on' => today()->addWeek()->toDateString(),
        ])->assertCreated();

        $this->actingAs($this->admin)->postJson('/api/fees/invoices/generate', [
            'school_id' => $this->school->id,
            'academic_year_id' => $year->id,
            'label' => 'Term 1',
        ])->assertOk();

        return FeeInvoice::query()->where('student_id', $this->child->id)->firstOrFail();
    }

    private function enableGateway(): void
    {
        $this->actingAs($this->admin)->putJson('/api/fees/gateway', [
            'school_id' => $this->school->id,
            'key_id' => 'rzp_test_abc123',
            'key_secret' => self::KEY_SECRET,
            'webhook_secret' => self::WEBHOOK_SECRET,
            'is_enabled' => true,
        ])->assertOk();
    }

    private function webhook(array $event, ?string $secret = self::WEBHOOK_SECRET, ?School $school = null)
    {
        $body = json_encode($event);

        return $this->call(
            'POST',
            '/api/webhooks/razorpay/'.($school ?? $this->school)->id,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, $secret),
            ],
            content: $body,
        );
    }

    private function paidEvent(string $linkId, string $paymentId, int $amount): array
    {
        return [
            'event' => 'payment_link.paid',
            'payload' => [
                'payment_link' => ['entity' => ['id' => $linkId, 'status' => 'paid']],
                'payment' => ['entity' => ['id' => $paymentId, 'amount' => $amount, 'status' => 'captured', 'created_at' => now()->getTimestamp()]],
            ],
        ];
    }

    private function callbackParams(string $linkId, string $paymentId, string $status = 'paid', string $secret = self::KEY_SECRET): array
    {
        $params = [
            'razorpay_payment_id' => $paymentId,
            'razorpay_payment_link_id' => $linkId,
            'razorpay_payment_link_reference_id' => 'EB-1',
            'razorpay_payment_link_status' => $status,
        ];

        return [...$params, 'razorpay_signature' => hash_hmac('sha256', implode('|', [$linkId, 'EB-1', $status, $paymentId]), $secret)];
    }

    // --- Gateway settings -----------------------------------------------------------

    public function test_admin_saves_gateway_keys_which_are_encrypted_and_never_returned(): void
    {
        $this->actingAs($this->admin)->getJson('/api/fees/gateway?school_id='.$this->school->id)
            ->assertOk()
            ->assertJsonPath('gateway.configured', false)
            ->assertJsonPath('gateway.webhook_url', url('/api/webhooks/razorpay/'.$this->school->id));

        // Switching on needs a webhook secret, then working keys.
        $this->actingAs($this->admin)->putJson('/api/fees/gateway', [
            'school_id' => $this->school->id, 'key_id' => 'rzp_test_abc123', 'key_secret' => self::KEY_SECRET, 'is_enabled' => true,
        ])->assertUnprocessable();

        $this->enableGateway();

        $response = $this->actingAs($this->admin)->getJson('/api/fees/gateway?school_id='.$this->school->id)
            ->assertOk()
            ->assertJsonPath('gateway.is_enabled', true)
            ->assertJsonPath('gateway.key_id', 'rzp_test_abc123')
            ->assertJsonPath('gateway.has_webhook_secret', true);
        $this->assertStringNotContainsString(self::KEY_SECRET, $response->getContent());
        $this->assertStringNotContainsString(self::WEBHOOK_SECRET, $response->getContent());

        $raw = DB::table('fee_gateway_accounts')->where('school_id', $this->school->id)->first();
        $this->assertNotSame(self::KEY_SECRET, $raw->key_secret);
        $this->assertSame(self::KEY_SECRET, FeeGatewayAccount::query()->first()->key_secret);

        // Blank secret keeps the saved one; switching off needs no API call.
        $this->actingAs($this->admin)->putJson('/api/fees/gateway', [
            'school_id' => $this->school->id, 'key_id' => 'rzp_test_abc123', 'is_enabled' => false,
        ])->assertOk()->assertJsonPath('gateway.is_enabled', false);
        $this->assertSame(self::KEY_SECRET, FeeGatewayAccount::query()->first()->key_secret);
    }

    public function test_rejected_keys_are_not_saved(): void
    {
        $this->fakeRazorpay(['api.razorpay.com/v1/payments*' => Http::response(['error' => ['code' => 'BAD_REQUEST_ERROR']], 401)]);

        $this->actingAs($this->admin)->putJson('/api/fees/gateway', [
            'school_id' => $this->school->id, 'key_id' => 'rzp_live_wrong', 'key_secret' => 'nope',
            'webhook_secret' => 'x', 'is_enabled' => true,
        ])->assertUnprocessable()->assertJsonPath('message', __('edubridge.fee_gateway_keys_invalid'));

        $this->assertDatabaseCount('fee_gateway_accounts', 0);
    }

    public function test_only_this_schools_admin_manages_the_gateway(): void
    {
        $other = School::query()->create([
            'name' => 'Other School', 'code' => 'OTHER', 'type' => 'private',
            'approval_status' => School::APPROVAL_APPROVED, 'is_active' => true,
        ]);
        $otherAdmin = User::query()->create(['name' => 'Other Admin', 'phone' => '9000000009']);
        $otherAdmin->assignSchoolRole($other->id, 'school_admin');

        $this->actingAs($otherAdmin)->getJson('/api/fees/gateway?school_id='.$this->school->id)->assertForbidden();
        $this->actingAs($otherAdmin)->putJson('/api/fees/gateway', [
            'school_id' => $this->school->id, 'key_id' => 'rzp_test_abc123', 'key_secret' => 's', 'is_enabled' => false,
        ])->assertForbidden();
        $this->actingAs($this->parent)->getJson('/api/fees/gateway?school_id='.$this->school->id)->assertForbidden();
    }

    // --- Paying -----------------------------------------------------------------------

    public function test_pay_link_needs_an_enabled_gateway(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->parent)->getJson("/api/fees/students/{$this->child->id}")
            ->assertOk()->assertJsonPath('online_payment', false);
        $this->actingAs($this->parent)->postJson("/api/fees/invoices/{$invoice->id}/pay-link")
            ->assertUnprocessable()->assertJsonPath('message', __('edubridge.fee_online_unavailable'));

        Http::assertNothingSent();
    }

    public function test_parent_gets_a_link_for_the_balance_and_it_is_reused(): void
    {
        $invoice = $this->invoice();
        $this->enableGateway();

        $this->actingAs($this->parent)->getJson("/api/fees/students/{$this->child->id}")
            ->assertJsonPath('online_payment', true);

        $this->actingAs($this->parent)->postJson("/api/fees/invoices/{$invoice->id}/pay-link")
            ->assertOk()
            ->assertJsonPath('url', 'https://rzp.io/i/test1')
            ->assertJsonPath('amount_paise', 100000);
        $this->actingAs($this->parent)->postJson("/api/fees/invoices/{$invoice->id}/pay-link")
            ->assertOk()->assertJsonPath('url', 'https://rzp.io/i/test1');

        Http::assertSentCount(2); // key check + one link
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.razorpay.com/v1/payment_links'
            && $r['amount'] === 100000
            && $r['currency'] === 'INR'
            && $r['accept_partial'] === false
            && $r['notes']['fee_invoice_id'] === (string) $invoice->id
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_abc123:'.self::KEY_SECRET)));

        // Another family's parent cannot make a link for this invoice.
        $stranger = User::query()->create(['name' => 'Stranger', 'phone' => '9000000010']);
        $stranger->assignSchoolRole($this->school->id, 'parent');
        $this->actingAs($stranger)->postJson("/api/fees/invoices/{$invoice->id}/pay-link")->assertForbidden();
    }

    public function test_webhook_records_the_payment_once(): void
    {
        $invoice = $this->invoice();
        $this->enableGateway();
        $this->actingAs($this->parent)->postJson("/api/fees/invoices/{$invoice->id}/pay-link")->assertOk();

        $event = $this->paidEvent('plink_test1', 'pay_ABC', 100000);

        $this->webhook($event, secret: 'forged')->assertStatus(400);
        $this->assertDatabaseCount('fee_payments', 0);

        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk(); // gateway retry

        $payment = FeePayment::query()->sole();
        $this->assertSame('online', $payment->method);
        $this->assertSame('pay_ABC', $payment->gateway_payment_id);
        $this->assertNull($payment->received_by);
        $this->assertSame('RCT/2025-26/0001', $payment->receipt_number);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('paid', FeePaymentLink::query()->sole()->status);

        // Parents get the WhatsApp receipt (queue is sync in tests).
        $this->assertSame(1, NotificationLog::query()->where('type', 'fee_receipt')->count());
    }

    public function test_webhook_for_another_school_is_rejected(): void
    {
        $invoice = $this->invoice();
        $this->enableGateway();
        $this->actingAs($this->parent)->postJson("/api/fees/invoices/{$invoice->id}/pay-link")->assertOk();

        $other = School::query()->create([
            'name' => 'Other School', 'code' => 'OTHER', 'type' => 'private',
            'approval_status' => School::APPROVAL_APPROVED, 'is_active' => true,
        ]);

        // No gateway at that school, so nothing can be signed for it.
        $this->webhook($this->paidEvent('plink_test1', 'pay_ABC', 100000), school: $other)->assertStatus(400);
        $this->assertDatabaseCount('fee_payments', 0);
    }

    public function test_browser_return_records_payment_and_webhook_does_not_duplicate_it(): void
    {
        $invoice = $this->invoice();
        $this->enableGateway();
        $this->actingAs($this->parent)->postJson("/api/fees/invoices/{$invoice->id}/pay-link")->assertOk();

        $this->postJson('/api/fees/online/confirm', $this->callbackParams('plink_test1', 'pay_XYZ', secret: 'forged'))
            ->assertUnprocessable();

        // No login needed: the signature is the proof.
        $this->postJson('/api/fees/online/confirm', $this->callbackParams('plink_test1', 'pay_XYZ'))
            ->assertOk()
            ->assertJsonPath('paid', true)
            ->assertJsonPath('student_id', $this->child->id);

        $this->webhook($this->paidEvent('plink_test1', 'pay_XYZ', 100000))->assertOk();

        $this->assertSame(1, FeePayment::query()->count());
        $this->assertSame(100000, $invoice->fresh()->paid_paise);

        // A cancelled payment page records nothing.
        $this->postJson('/api/fees/online/confirm', $this->callbackParams('plink_test1', '', 'cancelled'))
            ->assertOk()->assertJsonPath('paid', false);
    }

    public function test_office_payment_cancels_the_open_link_and_next_link_is_for_the_new_balance(): void
    {
        $invoice = $this->invoice();
        $this->enableGateway();
        $this->actingAs($this->parent)->postJson("/api/fees/invoices/{$invoice->id}/pay-link")->assertOk();

        $this->actingAs($this->admin)->postJson("/api/fees/invoices/{$invoice->id}/payments", [
            'amount_paise' => 40000, 'method' => 'cash',
        ])->assertCreated();

        $this->assertSame('cancelled', FeePaymentLink::query()->where('gateway_link_id', 'plink_test1')->value('status'));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.razorpay.com/v1/payment_links/plink_test1/cancel');

        $this->actingAs($this->parent)->postJson("/api/fees/invoices/{$invoice->id}/pay-link")
            ->assertOk()
            ->assertJsonPath('url', 'https://rzp.io/i/test2')
            ->assertJsonPath('amount_paise', 60000);
    }

    public function test_late_payment_on_a_voided_invoice_is_kept_for_refund(): void
    {
        $invoice = $this->invoice();
        $this->enableGateway();
        $this->actingAs($this->parent)->postJson("/api/fees/invoices/{$invoice->id}/pay-link")->assertOk();

        // The gateway refuses to cancel (already paid), so the link stays open.
        $this->fakeRazorpay(['api.razorpay.com/v1/payment_links/*/cancel' => Http::response(['error' => []], 400)]);
        $this->actingAs($this->admin)->postJson("/api/fees/invoices/{$invoice->id}/void", ['reason' => 'Duplicate'])->assertOk();

        $this->webhook($this->paidEvent('plink_test1', 'pay_LATE', 100000))->assertOk();

        $this->assertSame('void', $invoice->fresh()->status);
        $this->assertSame('paid', FeePaymentLink::query()->sole()->status);
        $this->assertSame('pay_LATE', FeePayment::query()->sole()->gateway_payment_id);
    }

    public function test_link_expiry_webhook_closes_the_link(): void
    {
        $invoice = $this->invoice();
        $this->enableGateway();
        $this->actingAs($this->parent)->postJson("/api/fees/invoices/{$invoice->id}/pay-link")->assertOk();

        $this->webhook([
            'event' => 'payment_link.expired',
            'payload' => ['payment_link' => ['entity' => ['id' => 'plink_test1', 'status' => 'expired']]],
        ])->assertOk();

        $this->assertSame('expired', FeePaymentLink::query()->sole()->status);
    }

    // --- Per-school reminders ---------------------------------------------------------

    public function test_reminder_timing_is_per_school_and_mentions_online_payment(): void
    {
        $invoice = $this->invoice(); // due in 7 days; default reminder is 3 days before

        $this->actingAs($this->admin)->getJson('/api/fees/setup?school_id='.$this->school->id)
            ->assertJsonPath('reminders.reminder_days_before', 3)
            ->assertJsonPath('reminders.enabled', true);

        $this->artisan('fees:send-reminders')->assertSuccessful();
        $this->assertNull($invoice->fresh()->last_reminded_at);

        $this->actingAs($this->admin)->putJson('/api/fees/reminders', [
            'school_id' => $this->school->id,
            'enabled' => false,
            'reminder_days_before' => 10,
            'overdue_every_days' => 5,
            'overdue_stop_after_days' => 30,
        ])->assertOk()->assertJsonPath('reminders.reminder_days_before', 10);

        $this->artisan('fees:send-reminders')->assertSuccessful();
        $this->assertNull($invoice->fresh()->last_reminded_at);

        $this->actingAs($this->admin)->putJson('/api/fees/reminders', [
            'school_id' => $this->school->id,
            'enabled' => true,
            'reminder_days_before' => 10,
            'overdue_every_days' => 5,
            'overdue_stop_after_days' => 30,
        ])->assertOk();
        $this->enableGateway();

        $this->artisan('fees:send-reminders')->assertSuccessful();

        $reminder = NotificationLog::query()->where('type', 'fee_reminder')->sole();
        $this->assertStringContainsString(url("/students/{$this->child->id}/fees"), $reminder->payload);
    }
}
