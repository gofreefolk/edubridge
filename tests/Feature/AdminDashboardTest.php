<?php

namespace Tests\Feature;

use App\Models\CentreLog;
use App\Models\ChecklistTemplate;
use App\Models\NotificationLog;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PilotSchoolSeeder::class);
        $this->school = School::query()->where('code', 'STMS-LP')->firstOrFail();
        $this->admin = User::query()->where('phone', '9876543210')->firstOrFail();
    }

    private function dashboard(?User $as = null, bool $refresh = false)
    {
        return $this->actingAs($as ?? $this->admin)
            ->getJson('/api/admin/dashboard?school_id='.$this->school->id.($refresh ? '&refresh=1' : ''));
    }

    public function test_summarises_todays_school_activity(): void
    {
        $seededClass = SchoolClass::query()->where('school_id', $this->school->id)->firstOrFail();
        $class4 = SchoolClass::query()->create([
            'school_id' => $this->school->id,
            'academic_year_id' => $seededClass->academic_year_id,
            'name' => '4',
            'sort_order' => 4,
        ]);
        Student::query()->create([
            'school_id' => $this->school->id,
            'school_class_id' => $class4->id,
            'name' => 'Unmarked Child',
            'status' => 'active',
        ]);

        ChecklistTemplate::query()->create([
            'school_id' => $this->school->id,
            'name' => 'Morning safety walk',
            'frequency' => 'daily',
            'items' => ['Gate locked', 'First aid kit stocked'],
        ]);

        CentreLog::query()->create([
            'school_id' => $this->school->id,
            'author_id' => $this->admin->id,
            'category' => 'incident',
            'title' => 'Fall in playground',
            'body' => 'Minor scrape, cleaned.',
            'occurred_at' => now(),
        ]);

        NotificationLog::query()->create([
            'school_id' => $this->school->id,
            'channel' => 'whatsapp',
            'type' => 'absence_alert',
            'status' => 'failed',
        ]);

        $this->dashboard()
            ->assertOk()
            ->assertJsonPath('date', today()->toDateString())
            // Seeded child is present today; the new class has nobody marked.
            ->assertJsonPath('attendance.present', 1)
            ->assertJsonPath('attendance.marked', 1)
            ->assertJsonPath('attendance.students', 2)
            ->assertJsonPath('attendance.percent', 100)
            ->assertJsonPath('attendance.unmarked_classes', ['4'])
            ->assertJsonPath('notices.published_recent', 2)
            ->assertJsonPath('feedback.acknowledged', 1)
            ->assertJsonPath('checklists.total', 1)
            ->assertJsonPath('checklists.done', 0)
            ->assertJsonPath('checklists.pending.0.name', 'Morning safety walk')
            ->assertJsonPath('centre_logs.incidents_recent', 1)
            ->assertJsonPath('delivery.failed', 1);
    }

    public function test_adoption_counts_parents_who_read_a_notice(): void
    {
        $this->dashboard()
            ->assertJsonPath('adoption.parents', 1)
            ->assertJsonPath('adoption.readers', 0);

        $parent = User::query()->where('phone', '9123456789')->firstOrFail();
        $this->actingAs($parent)->postJson('/api/notices/magic/demo123abc/read')->assertOk();

        // Cached for a minute; refresh=1 recomputes.
        $this->dashboard()->assertJsonPath('adoption.readers', 0);
        $this->dashboard(refresh: true)
            ->assertJsonPath('adoption.readers', 1)
            ->assertJsonPath('adoption.percent', 100);
    }

    public function test_adoption_counts_parents_who_opened_the_app(): void
    {
        $this->dashboard()
            ->assertJsonPath('adoption.seen', 0)
            ->assertJsonPath('adoption.active_recent', 0);

        $parent = User::query()->where('phone', '9123456789')->firstOrFail();
        $this->actingAs($parent)->getJson('/api/auth/me')->assertOk();
        $this->assertNotNull($parent->fresh()->last_seen_at);

        $this->dashboard(refresh: true)
            ->assertJsonPath('adoption.seen', 1)
            ->assertJsonPath('adoption.active_recent', 1);

        $this->travel(10)->days();

        $this->dashboard(refresh: true)
            ->assertJsonPath('adoption.seen', 1)
            ->assertJsonPath('adoption.active_recent', 0);
    }

    public function test_only_this_schools_admins_can_view(): void
    {
        $this->dashboard(User::query()->where('phone', '9876501234')->firstOrFail())->assertForbidden();
        $this->dashboard(User::query()->where('phone', '9123456789')->firstOrFail())->assertForbidden();

        $other = School::query()->create([
            'name' => 'Other School',
            'code' => 'OTHER',
            'type' => 'private',
            'approval_status' => School::APPROVAL_APPROVED,
            'is_active' => true,
        ]);
        $otherAdmin = User::query()->create(['name' => 'Other Admin', 'phone' => '9000000009']);
        $otherAdmin->assignSchoolRole($other->id, 'school_admin');

        $this->dashboard($otherAdmin)->assertForbidden();
    }

    public function test_reports_export_as_csv(): void
    {
        $class = SchoolClass::query()->where('school_id', $this->school->id)->firstOrFail();

        $attendance = $this->actingAs($this->admin)
            ->get('/api/attendance/report?format=csv&school_class_id='.$class->id.'&month='.now()->format('Y-m'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $attendance->streamedContent();
        $this->assertStringContainsString('Student,"Admission no."', $csv);
        $this->assertStringContainsString('Anu,STMS-2025-042,1,0,0,0,1,100', $csv);

        ChecklistTemplate::query()->create([
            'school_id' => $this->school->id,
            'name' => '=HYPERLINK("evil")',
            'frequency' => 'weekly',
            'items' => ['One'],
        ]);

        $checklists = $this->actingAs($this->admin)
            ->get('/api/checklists/report?format=csv&school_id='.$this->school->id
                .'&from='.today()->toDateString().'&to='.today()->toDateString())
            ->assertOk()
            ->streamedContent();

        // Formula-looking text is escaped so spreadsheets show it as text.
        $this->assertStringContainsString("\"'=HYPERLINK(\"\"evil\"\")\"", $checklists);
    }
}
