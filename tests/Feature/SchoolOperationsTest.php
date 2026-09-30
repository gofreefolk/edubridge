<?php

namespace Tests\Feature;

use App\Jobs\SendAbsenceAlert;
use App\Models\AttendanceRecord;
use App\Models\CentreLog;
use App\Models\ChecklistTemplate;
use App\Models\NotificationLog;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppOptIn;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SchoolOperationsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private User $teacher;

    private User $parent;

    private Student $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PilotSchoolSeeder::class);
        $this->school = School::query()->where('code', 'STMS-LP')->firstOrFail();
        $this->admin = User::query()->where('phone', '9876543210')->firstOrFail();
        $this->teacher = User::query()->where('phone', '9876501234')->firstOrFail();
        $this->parent = User::query()->where('phone', '9123456789')->firstOrFail();
        $this->child = $this->parent->children()->firstOrFail();
    }

    private function otherParent(): User
    {
        $user = User::query()->create(['name' => 'Other Parent', 'phone' => '9000000021']);
        $user->assignSchoolRole($this->school->id, 'parent');

        return $user;
    }

    // --- Student profile & contacts -------------------------------------------------

    public function test_admin_updates_profile_and_manages_pickup_contacts(): void
    {
        $this->actingAs($this->admin)
            ->putJson("/api/students/{$this->child->id}/profile", [
                'blood_group' => 'O+',
                'allergies' => 'Peanuts',
            ])
            ->assertOk()
            ->assertJsonPath('student.blood_group', 'O+')
            ->assertJsonPath('student.allergies', 'Peanuts');

        $response = $this->actingAs($this->admin)
            ->postJson("/api/students/{$this->child->id}/contacts", [
                'name' => 'Uncle Joseph',
                'relationship' => 'uncle',
                'phone' => '+91 98470 11111',
                'can_pickup' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('student.contacts.0.phone', '9847011111')
            ->assertJsonPath('student.contacts.0.can_pickup', true);

        $contactId = $response->json('student.contacts.0.id');

        $this->actingAs($this->admin)
            ->deleteJson("/api/students/{$this->child->id}/contacts/{$contactId}")
            ->assertOk()
            ->assertJsonCount(0, 'student.contacts');
    }

    public function test_parent_can_read_own_childs_profile_but_not_edit_or_see_others(): void
    {
        $this->actingAs($this->parent)
            ->getJson("/api/students/{$this->child->id}/profile")
            ->assertOk()
            ->assertJsonPath('can_edit', false);

        $this->actingAs($this->parent)
            ->putJson("/api/students/{$this->child->id}/profile", ['blood_group' => 'A+'])
            ->assertForbidden();

        $this->actingAs($this->otherParent())
            ->getJson("/api/students/{$this->child->id}/profile")
            ->assertForbidden();
    }

    // --- Attendance -----------------------------------------------------------------

    public function test_marking_absent_today_queues_one_alert_and_sheet_reflects_it(): void
    {
        Queue::fake();

        $this->actingAs($this->teacher)
            ->postJson('/api/teacher/attendance', [
                'school_id' => $this->school->id,
                'date' => today()->toDateString(),
                'records' => [['student_id' => $this->child->id, 'status' => 'absent']],
            ])
            ->assertOk()
            ->assertJsonPath('absence_alerts_queued', 1);

        Queue::assertPushed(SendAbsenceAlert::class, 1);

        // Saving the same register again must update, not duplicate.
        $this->actingAs($this->teacher)
            ->postJson('/api/teacher/attendance', [
                'school_id' => $this->school->id,
                'date' => today()->toDateString(),
                'records' => [['student_id' => $this->child->id, 'status' => 'present']],
            ])
            ->assertOk();

        $this->assertSame(1, AttendanceRecord::query()->where('student_id', $this->child->id)->whereDate('date', today())->count());

        $this->actingAs($this->teacher)
            ->getJson("/api/attendance/sheet?school_class_id={$this->child->school_class_id}&section_id={$this->child->section_id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $this->child->id, 'status' => 'present']);
    }

    public function test_absence_alert_is_sent_once_to_opted_in_parents(): void
    {
        WhatsAppOptIn::query()->updateOrCreate(
            ['user_id' => $this->parent->id, 'school_id' => $this->school->id],
            ['opted_in' => true, 'opted_in_at' => now()],
        );

        // The pilot seeder already marks today's attendance for this child.
        AttendanceRecord::query()->where('student_id', $this->child->id)->delete();

        $record = AttendanceRecord::query()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->child->id,
            'date' => today(),
            'status' => 'absent',
        ]);

        SendAbsenceAlert::dispatchSync($record->id);
        SendAbsenceAlert::dispatchSync($record->id);

        $this->assertSame(1, NotificationLog::query()
            ->where('user_id', $this->parent->id)
            ->where('type', 'absence_alert')
            ->count());
        $this->assertNotNull($record->fresh()->absence_alert_sent_at);
    }

    public function test_monthly_attendance_report(): void
    {
        AttendanceRecord::query()->where('student_id', $this->child->id)->delete();

        foreach (['present', 'absent', 'late', 'present'] as $i => $status) {
            AttendanceRecord::query()->create([
                'school_id' => $this->school->id,
                'student_id' => $this->child->id,
                'date' => today()->startOfMonth()->addDays($i),
                'status' => $status,
            ]);
        }

        $response = $this->actingAs($this->teacher)
            ->getJson("/api/attendance/report?school_class_id={$this->child->school_class_id}&month=".today()->format('Y-m'))
            ->assertOk();

        $row = collect($response->json('students'))->firstWhere('id', $this->child->id);

        $this->assertSame(1, $row['absent']);
        $this->assertSame(4, $row['marked_days']);
        $this->assertEquals(75.0, $row['percent']); // present + late count as attended

        $this->actingAs($this->parent)
            ->getJson("/api/attendance/report?school_class_id={$this->child->school_class_id}&month=".today()->format('Y-m'))
            ->assertForbidden();
    }

    // --- Centre log -------------------------------------------------------------------

    public function test_parents_see_only_shared_logs_about_their_child(): void
    {
        $this->actingAs($this->teacher)
            ->postJson('/api/centre-logs', [
                'school_id' => $this->school->id,
                'student_id' => $this->child->id,
                'category' => 'incident',
                'title' => 'Scraped knee',
                'body' => 'Fell during play; cleaned and plaster applied.',
                'visible_to_parents' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('log.class', $this->child->schoolClass->name);

        $this->actingAs($this->teacher)
            ->postJson('/api/centre-logs', [
                'school_id' => $this->school->id,
                'category' => 'daily',
                'title' => 'Staff-only note',
                'body' => 'Internal.',
            ])
            ->assertCreated();

        $this->assertSame(['Scraped knee'], $this->actingAs($this->parent)
            ->getJson("/api/centre-logs?school_id={$this->school->id}")
            ->assertOk()
            ->json('logs.*.title'));

        $this->assertSame([], $this->actingAs($this->otherParent())
            ->getJson("/api/centre-logs?school_id={$this->school->id}")
            ->assertOk()
            ->json('logs'));

        $this->assertCount(2, $this->actingAs($this->admin)
            ->getJson("/api/centre-logs?school_id={$this->school->id}")
            ->json('logs'));

        $this->actingAs($this->parent)
            ->postJson('/api/centre-logs', [
                'school_id' => $this->school->id,
                'category' => 'daily',
                'title' => 'x',
                'body' => 'x',
            ])
            ->assertForbidden();

        $log = CentreLog::query()->where('title', 'Scraped knee')->firstOrFail();
        $this->actingAs($this->parent)->deleteJson("/api/centre-logs/{$log->id}")->assertForbidden();
        $this->actingAs($this->admin)->deleteJson("/api/centre-logs/{$log->id}")->assertOk();
    }

    // --- Checklists -------------------------------------------------------------------

    public function test_checklist_lifecycle_and_report(): void
    {
        $template = $this->actingAs($this->admin)
            ->postJson('/api/checklists', [
                'school_id' => $this->school->id,
                'name' => 'Morning safety walk',
                'frequency' => 'daily',
                'items' => ['Gate locked', 'First-aid kit stocked', 'Classrooms clear'],
            ])
            ->assertCreated()
            ->json('checklist');

        $this->actingAs($this->teacher)
            ->postJson('/api/checklists', [
                'school_id' => $this->school->id,
                'name' => 'x',
                'frequency' => 'daily',
                'items' => ['a'],
            ])
            ->assertForbidden();

        $this->actingAs($this->teacher)
            ->postJson("/api/checklists/{$template['id']}/submit", [
                'responses' => [['done' => true], ['done' => false, 'note' => 'Needs refill'], ['done' => true]],
            ])
            ->assertOk()
            ->assertJsonPath('submission.done_count', 2)
            ->assertJsonPath('submission.total_count', 3);

        // Wrong number of responses is rejected.
        $this->actingAs($this->teacher)
            ->postJson("/api/checklists/{$template['id']}/submit", ['responses' => [['done' => true]]])
            ->assertUnprocessable();

        $this->actingAs($this->teacher)
            ->getJson("/api/checklists?school_id={$this->school->id}")
            ->assertOk()
            ->assertJsonPath('checklists.0.submission.done_count', 2);

        $this->actingAs($this->parent)
            ->getJson("/api/checklists?school_id={$this->school->id}")
            ->assertForbidden();

        $report = $this->actingAs($this->admin)
            ->getJson("/api/checklists/report?school_id={$this->school->id}&from=".today()->toDateString().'&to='.today()->toDateString())
            ->assertOk()
            ->json('checklists.0');

        $expected = today()->isSunday() ? 0 : 1;
        $this->assertSame($expected, $report['expected']);
        $this->assertSame($expected, $report['completed']);

        $this->assertTrue(ChecklistTemplate::query()->whereKey($template['id'])->exists());
    }

    // --- Messaging --------------------------------------------------------------------

    public function test_teacher_can_start_thread_with_a_childs_parents(): void
    {
        $threadId = $this->actingAs($this->teacher)
            ->postJson('/api/feedback', [
                'school_id' => $this->school->id,
                'student_id' => $this->child->id,
                'category' => 'academic',
                'subject' => 'Reading progress',
                'body' => 'Anu is reading very well this week.',
                'direction' => 'teacher_to_parent',
            ])
            ->assertCreated()
            ->json('thread.id');

        $this->actingAs($this->parent)
            ->getJson("/api/feedback?school_id={$this->school->id}")
            ->assertOk()
            ->assertJsonFragment(['subject' => 'Reading progress']);

        $this->actingAs($this->parent)
            ->postJson("/api/feedback/{$threadId}/reply", ['body' => 'Thank you!'])
            ->assertOk();

        $this->actingAs($this->otherParent())
            ->getJson("/api/feedback/{$threadId}")
            ->assertForbidden();

        // Parents cannot use the staff direction.
        $this->actingAs($this->parent)
            ->postJson('/api/feedback', [
                'school_id' => $this->school->id,
                'student_id' => $this->child->id,
                'category' => 'general',
                'subject' => 'x',
                'body' => 'x',
                'direction' => 'teacher_to_parent',
            ])
            ->assertForbidden();
    }
}
