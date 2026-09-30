<?php

namespace Tests\Feature;

use App\Jobs\SendFeeReceiptWhatsApp;
use App\Jobs\SendFeeReminderWhatsApp;
use App\Models\AcademicYear;
use App\Models\FeeInvoice;
use App\Models\NotificationLog;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AcademicYear $year;

    private User $admin;

    private User $parent;

    private Student $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PilotSchoolSeeder::class);
        $this->school = School::query()->where('code', 'STMS-LP')->firstOrFail();
        $this->year = AcademicYear::query()->where('school_id', $this->school->id)->firstOrFail();
        $this->admin = User::query()->where('phone', '9876543210')->firstOrFail();
        $this->parent = User::query()->where('phone', '9123456789')->firstOrFail();
        $this->child = $this->parent->children()->firstOrFail();
    }

    private function feeHead(string $name): int
    {
        return $this->actingAs($this->admin)
            ->postJson('/api/fees/heads', ['school_id' => $this->school->id, 'name' => $name])
            ->assertCreated()
            ->json('head.id');
    }

    private function structure(int $headId, int $amountPaise, ?int $classId, string $dueOn, string $label = 'Term 1'): void
    {
        $this->actingAs($this->admin)->postJson('/api/fees/structures', [
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $classId,
            'fee_head_id' => $headId,
            'label' => $label,
            'amount_paise' => $amountPaise,
            'due_on' => $dueOn,
        ])->assertCreated();
    }

    /** Tuition ₹1,500 for the child's class + Bus ₹500 for everyone, 50% tuition concession. */
    private function billTerm1(string $dueOn = '2026-10-15'): FeeInvoice
    {
        $tuition = $this->feeHead('Tuition');
        $this->structure($tuition, 150000, $this->child->school_class_id, $dueOn);
        $this->structure($this->feeHead('Bus'), 50000, null, $dueOn);

        $this->actingAs($this->admin)->postJson("/api/fees/students/{$this->child->id}/concessions", [
            'fee_head_id' => $tuition,
            'type' => 'percent',
            'value' => 50,
            'reason' => 'Sibling concession',
        ])->assertCreated();

        $this->actingAs($this->admin)->postJson('/api/fees/invoices/generate', [
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'label' => 'Term 1',
            'issued_on' => '2026-09-30',
        ])->assertOk()->assertJson(['created' => 1, 'skipped' => 0]);

        return FeeInvoice::query()->where('student_id', $this->child->id)->firstOrFail();
    }

    private function pay(FeeInvoice $invoice, int $paise)
    {
        return $this->actingAs($this->admin)->postJson("/api/fees/invoices/{$invoice->id}/payments", [
            'amount_paise' => $paise,
            'method' => 'upi',
            'reference' => 'UPI123',
        ]);
    }

    // --- Numbering ------------------------------------------------------------------

    public function test_numbering_defaults_and_is_configurable_per_school(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/fees/setup?school_id='.$this->school->id)
            ->assertOk()
            ->assertJsonPath('numbering.invoice.preview', 'INV/2025-26/0001')
            ->assertJsonPath('numbering.receipt.preview', 'RCT/2025-26/0001');

        $this->actingAs($this->admin)->putJson('/api/fees/numbering', [
            'school_id' => $this->school->id,
            'type' => 'receipt',
            'format' => '{CODE}-{YYYY}{MM}-{SEQ:5}',
            'reset' => 'never',
            'next_number' => 1501, // continuing from the paper receipt book
        ])->assertOk()->assertJsonPath('numbering.preview', 'STMS-LP-'.now()->format('Ym').'-01501');

        foreach (['R-{YYYY}', '{SEQ}-{SEQ}', 'R/{SEQ}/{FOO}', 'R<{SEQ}>'] as $bad) {
            $this->actingAs($this->admin)->putJson('/api/fees/numbering', [
                'school_id' => $this->school->id, 'type' => 'invoice', 'format' => $bad, 'reset' => 'never',
            ])->assertUnprocessable();
        }

        $invoice = $this->billTerm1();
        $this->assertSame('INV/2025-26/0001', $invoice->number);

        $this->pay($invoice, 10000)->assertCreated()
            ->assertJsonPath('receipt_number', 'STMS-LP-'.now()->format('Ym').'-01501');
        $this->pay($invoice, 10000)->assertCreated()
            ->assertJsonPath('receipt_number', 'STMS-LP-'.now()->format('Ym').'-01502');
    }

    public function test_allocator_skips_numbers_already_used(): void
    {
        $invoice = $this->billTerm1();
        $this->pay($invoice, 10000)->assertJsonPath('receipt_number', 'RCT/2025-26/0001');

        // Admin winds the counter back by mistake: the next receipt must not collide.
        $this->actingAs($this->admin)->putJson('/api/fees/numbering', [
            'school_id' => $this->school->id, 'type' => 'receipt', 'format' => 'RCT/{AY}/{SEQ:4}',
            'reset' => 'academic_year', 'next_number' => 1,
        ])->assertOk();

        $this->pay($invoice, 10000)->assertJsonPath('receipt_number', 'RCT/2025-26/0002');
    }

    // --- Invoices -------------------------------------------------------------------

    public function test_generates_invoice_with_concession_and_is_safe_to_rerun(): void
    {
        $invoice = $this->billTerm1();

        $this->assertSame(200000, $invoice->total_paise);
        $this->assertSame(75000, $invoice->discount_paise);
        $this->assertSame(125000, $invoice->netPaise());
        $this->assertSame('2026-10-15', $invoice->due_on->toDateString());
        $this->assertCount(2, $invoice->lines);

        $this->actingAs($this->admin)->postJson('/api/fees/invoices/generate', [
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'label' => 'Term 1',
        ])->assertOk()->assertJson(['created' => 0, 'skipped' => 1]);

        $this->actingAs($this->admin)->postJson('/api/fees/invoices/generate', [
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'label' => 'Term 9',
        ])->assertUnprocessable();
    }

    public function test_payments_update_status_and_cannot_exceed_balance(): void
    {
        Queue::fake();
        $invoice = $this->billTerm1();

        $this->pay($invoice, 50000)->assertCreated()
            ->assertJsonPath('invoice.status', 'partially_paid')
            ->assertJsonPath('invoice.balance_paise', 75000);
        Queue::assertPushed(SendFeeReceiptWhatsApp::class, 1);

        $this->pay($invoice, 75001)->assertUnprocessable();

        $response = $this->pay($invoice, 75000)->assertCreated()
            ->assertJsonPath('invoice.status', 'paid')
            ->assertJsonPath('invoice.balance_paise', 0);

        // Voiding a payment reopens the balance; the invoice can't be voided while paid.
        $this->actingAs($this->admin)
            ->postJson("/api/fees/invoices/{$invoice->id}/void", ['reason' => 'Wrong student'])
            ->assertUnprocessable();

        $this->actingAs($this->admin)
            ->postJson("/api/fees/payments/{$response->json('payment_id')}/void", ['reason' => 'Cheque bounced'])
            ->assertOk()
            ->assertJsonPath('invoice.status', 'partially_paid')
            ->assertJsonPath('invoice.balance_paise', 75000)
            ->assertJsonPath('invoice.payments.1.voided', true);
    }

    public function test_voided_invoice_can_be_regenerated(): void
    {
        $invoice = $this->billTerm1();

        $this->actingAs($this->admin)
            ->postJson("/api/fees/invoices/{$invoice->id}/void", ['reason' => 'Wrong amount'])
            ->assertOk()
            ->assertJsonPath('invoice.status', 'void');

        $this->actingAs($this->admin)->postJson('/api/fees/invoices/generate', [
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'label' => 'Term 1',
        ])->assertOk()->assertJson(['created' => 1]);

        $this->assertSame('INV/2025-26/0002', FeeInvoice::query()->latest('id')->value('number'));
    }

    // --- Access ---------------------------------------------------------------------

    public function test_parents_see_only_their_childs_fees(): void
    {
        $invoice = $this->billTerm1();
        $paymentId = $this->pay($invoice, 50000)->json('payment_id');

        $this->actingAs($this->parent)
            ->getJson("/api/fees/students/{$this->child->id}")
            ->assertOk()
            ->assertJsonPath('totals.balance_paise', 75000)
            ->assertJsonPath('invoices.0.number', 'INV/2025-26/0001');

        $this->actingAs($this->parent)
            ->getJson("/api/fees/payments/{$paymentId}/receipt")
            ->assertOk()
            ->assertJsonPath('receipt.amount_paise', 50000)
            ->assertJsonPath('receipt.student.name', $this->child->name);

        $stranger = User::query()->create(['name' => 'Other Parent', 'phone' => '9000000031']);
        $stranger->assignSchoolRole($this->school->id, 'parent');
        $teacher = User::query()->where('phone', '9876501234')->firstOrFail();

        foreach ([$stranger, $teacher] as $user) {
            $this->actingAs($user)->getJson("/api/fees/students/{$this->child->id}")->assertForbidden();
            $this->actingAs($user)->getJson("/api/fees/payments/{$paymentId}/receipt")->assertForbidden();
        }

        $this->actingAs($this->parent)->postJson("/api/fees/invoices/{$invoice->id}/payments", [
            'amount_paise' => 100, 'method' => 'cash',
        ])->assertForbidden();
    }

    public function test_other_schools_admin_cannot_touch_fees(): void
    {
        $invoice = $this->billTerm1();

        $other = School::query()->create([
            'name' => 'Other School', 'code' => 'OTHER', 'type' => 'private',
            'approval_status' => School::APPROVAL_APPROVED, 'is_active' => true,
        ]);
        $otherAdmin = User::query()->create(['name' => 'Other Admin', 'phone' => '9000000009']);
        $otherAdmin->assignSchoolRole($other->id, 'school_admin');

        $this->actingAs($otherAdmin)->getJson('/api/fees/setup?school_id='.$this->school->id)->assertForbidden();
        $this->actingAs($otherAdmin)->getJson('/api/fees/invoices?school_id='.$this->school->id)->assertForbidden();
        $this->actingAs($otherAdmin)->getJson("/api/fees/invoices/{$invoice->id}")->assertForbidden();
        $this->actingAs($otherAdmin)->postJson("/api/fees/invoices/{$invoice->id}/payments", [
            'amount_paise' => 100, 'method' => 'cash',
        ])->assertForbidden();

        // Structures can't point at another school's year or heads.
        $this->actingAs($otherAdmin)->postJson('/api/fees/heads', ['school_id' => $other->id, 'name' => 'Tuition'])->assertCreated();
        $this->actingAs($otherAdmin)->postJson('/api/fees/structures', [
            'school_id' => $other->id,
            'academic_year_id' => $this->year->id,
            'fee_head_id' => $invoice->lines->first()->fee_head_id,
            'label' => 'Term 1',
            'amount_paise' => 100,
            'due_on' => '2026-10-01',
        ])->assertUnprocessable();
    }

    // --- Reports & reminders --------------------------------------------------------

    public function test_collection_and_outstanding_reports(): void
    {
        $invoice = $this->billTerm1(dueOn: today()->subDays(10)->toDateString());
        $this->pay($invoice, 25000)->assertCreated();

        $this->actingAs($this->admin)
            ->getJson('/api/fees/reports/collections?school_id='.$this->school->id.'&from='.today()->toDateString().'&to='.today()->toDateString())
            ->assertOk()
            ->assertJsonPath('total_paise', 25000)
            ->assertJsonPath('by_method.upi', 25000)
            ->assertJsonPath('payments.0.receipt_number', 'RCT/2025-26/0001');

        $this->actingAs($this->admin)
            ->getJson("/api/fees/reports/outstanding?school_id={$this->school->id}&academic_year_id={$this->year->id}")
            ->assertOk()
            ->assertJsonPath('totals.net_paise', 125000)
            ->assertJsonPath('totals.paid_paise', 25000)
            ->assertJsonPath('totals.overdue_paise', 100000)
            ->assertJsonPath('defaulters.0.days_overdue', 10)
            ->assertJsonPath('defaulters.0.phone', '9123456789');

        $csv = $this->actingAs($this->admin)
            ->get("/api/fees/reports/outstanding?format=csv&school_id={$this->school->id}&academic_year_id={$this->year->id}")
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('INV/2025-26/0001', $csv);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard?school_id='.$this->school->id)
            ->assertJsonPath('fees.collected_paise', 25000)
            ->assertJsonPath('fees.overdue_paise', 100000);
    }

    public function test_receipt_and_reminder_messages_reach_opted_in_parents(): void
    {
        $invoice = $this->billTerm1(dueOn: today()->subDay()->toDateString());
        $this->pay($invoice, 50000)->assertCreated(); // queue is sync in tests: job runs now

        $receipt = NotificationLog::query()->where('type', 'fee_receipt')->sole();
        $this->assertSame($this->parent->id, $receipt->user_id);
        $this->assertStringContainsString('₹500', $receipt->payload);
        $this->assertStringContainsString('RCT/2025-26/0001', $receipt->payload);
        $this->assertStringContainsString('₹750', $receipt->payload);

        $this->artisan('fees:send-reminders')->assertSuccessful();

        $reminder = NotificationLog::query()->where('type', 'fee_reminder')->sole();
        $this->assertStringContainsString('INV/2025-26/0001', $reminder->payload);
        $this->assertStringContainsString('₹750', $reminder->payload);
    }

    public function test_reminders_go_once_before_due_then_weekly_when_overdue(): void
    {
        Queue::fake();
        $invoice = $this->billTerm1(dueOn: today()->addDays(2)->toDateString());

        $this->artisan('fees:send-reminders')->assertSuccessful();
        $this->artisan('fees:send-reminders')->assertSuccessful();
        Queue::assertPushed(SendFeeReminderWhatsApp::class, 1);

        // Now overdue, last reminded 8 days ago → reminded again.
        $invoice->update(['due_on' => today()->subDays(3), 'last_reminded_at' => now()->subDays(8)]);
        $this->artisan('fees:send-reminders')->assertSuccessful();
        Queue::assertPushed(SendFeeReminderWhatsApp::class, 2);

        // Paid invoices are never reminded.
        $invoice->update(['status' => 'paid', 'last_reminded_at' => now()->subDays(8)]);
        $this->artisan('fees:send-reminders')->assertSuccessful();
        Queue::assertPushed(SendFeeReminderWhatsApp::class, 2);
    }
}
