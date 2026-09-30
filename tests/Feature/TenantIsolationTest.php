<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\FeedbackThread;
use App\Models\Notice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * A role at one school must never grant access to another school's data.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private School $pilot;

    private School $other;

    private User $otherAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PilotSchoolSeeder::class);
        $this->pilot = School::query()->where('code', 'STMS-LP')->firstOrFail();

        $this->other = School::query()->create([
            'name' => 'Other School',
            'code' => 'OTHER',
            'type' => 'private',
            'approval_status' => School::APPROVAL_APPROVED,
            'is_active' => true,
        ]);

        $this->otherAdmin = User::query()->create(['name' => 'Other Admin', 'phone' => '9000000009']);
        $this->otherAdmin->assignSchoolRole($this->other->id, 'school_admin');
    }

    private function user(string $phone): User
    {
        return User::query()->where('phone', $phone)->firstOrFail();
    }

    private function pilotChild(): Student
    {
        return $this->user('9123456789')->children()->firstOrFail();
    }

    public function test_admin_cannot_list_another_schools_notices(): void
    {
        $this->actingAs($this->otherAdmin)
            ->getJson('/api/notices?school_id='.$this->pilot->id)
            ->assertForbidden();
    }

    public function test_admin_cannot_publish_another_schools_notice(): void
    {
        $notice = Notice::query()->where('school_id', $this->pilot->id)->firstOrFail();
        $notice->update(['status' => 'draft', 'published_at' => null]);

        $this->actingAs($this->otherAdmin)
            ->postJson("/api/notices/{$notice->id}/publish")
            ->assertForbidden();

        $this->assertSame('draft', $notice->fresh()->status);
    }

    public function test_admin_cannot_import_into_another_school(): void
    {
        $csv = "student_name,admission_number,class,section,parent_name,parent_phone\nX,EVIL1,3,B,Evil,9000000009\n";

        $this->actingAs($this->otherAdmin)
            ->post('/api/admin/import/parents', [
                'school_id' => $this->pilot->id,
                'file' => UploadedFile::fake()->createWithContent('a.csv', $csv),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertSame([], $this->otherAdmin->fresh()->rolesAtSchool($this->pilot->id));
    }

    public function test_user_without_role_at_school_sees_no_notices(): void
    {
        $alumniElsewhere = User::query()->create(['name' => 'Alumni', 'phone' => '9000000010']);
        $alumniElsewhere->assignSchoolRole($this->other->id, 'alumni');

        $this->actingAs($alumniElsewhere)
            ->getJson('/api/notices?school_id='.$this->pilot->id)
            ->assertForbidden();
    }

    public function test_parent_does_not_see_smc_only_notices(): void
    {
        $admin = $this->user('9876543210');
        Notice::query()->create([
            'school_id' => $this->pilot->id,
            'author_id' => $admin->id,
            'title' => 'Committee only',
            'body' => 'x',
            'audience_type' => 'smc_only',
            'status' => 'published',
            'published_at' => now(),
            'magic_link_token' => 'smconly12345',
        ]);

        $titles = $this->actingAs($this->user('9123456789'))
            ->getJson('/api/notices?school_id='.$this->pilot->id)
            ->assertOk()
            ->json('notices.*.title');

        $this->assertNotContains('Committee only', $titles);

        $smcTitles = $this->actingAs($this->user('9847012345'))
            ->getJson('/api/notices?school_id='.$this->pilot->id)
            ->assertOk()
            ->json('notices.*.title');

        $this->assertContains('Committee only', $smcTitles);
    }

    public function test_parent_can_open_own_childs_dashboard_but_not_others(): void
    {
        $parent = $this->user('9123456789');
        $child = $this->pilotChild();

        $this->actingAs($parent)
            ->getJson('/api/student/dashboard?student_id='.$child->id)
            ->assertOk();

        $stranger = Student::query()->create([
            'school_id' => $this->pilot->id,
            'name' => 'Someone else',
            'school_class_id' => $child->school_class_id,
            'section_id' => $child->section_id,
        ]);

        $this->actingAs($parent)
            ->getJson('/api/student/dashboard?student_id='.$stranger->id)
            ->assertForbidden();

        $this->actingAs($parent)
            ->getJson('/api/transport/student?student_id='.$stranger->id)
            ->assertForbidden();
    }

    public function test_assigning_second_role_keeps_existing_role(): void
    {
        $this->actingAs($this->user('9876543210'))
            ->postJson('/api/admin/staff', [
                'school_id' => $this->pilot->id,
                'name' => 'Parent Teacher',
                'phone' => '9123456789',
                'role' => 'teacher',
            ])
            ->assertCreated();

        $roles = $this->user('9123456789')->rolesAtSchool($this->pilot->id);
        sort($roles);

        $this->assertSame(['parent', 'teacher'], $roles);
    }

    public function test_csv_import_does_not_demote_staff(): void
    {
        $csv = "student_name,admission_number,class,section,parent_name,parent_phone\nTeacher Kid,TK-1,3,B,Teacher,9876501234\n";

        $this->actingAs($this->user('9876543210'))
            ->post('/api/admin/import/parents', [
                'school_id' => $this->pilot->id,
                'file' => UploadedFile::fake()->createWithContent('a.csv', $csv),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('imported', 1);

        $teacher = $this->user('9876501234');
        $this->assertTrue($teacher->hasRoleAtSchool($this->pilot->id, 'teacher'));
        $this->assertTrue($teacher->hasRoleAtSchool($this->pilot->id, 'parent'));
    }

    public function test_teacher_cannot_read_another_schools_roster(): void
    {
        $otherClass = SchoolClass::query()->create([
            'school_id' => $this->other->id,
            'academic_year_id' => $this->other->academicYears()->create([
                'name' => '2026-27', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'is_current' => true,
            ])->id,
            'name' => 'Class 1',
        ]);

        $this->actingAs($this->user('9876501234'))
            ->getJson('/api/teacher/roster?school_class_id='.$otherClass->id)
            ->assertForbidden();
    }

    public function test_exam_attempt_hides_answers_and_is_limited_to_own_child(): void
    {
        $exam = Exam::query()->where('school_id', $this->pilot->id)->firstOrFail();
        $child = $this->pilotChild();

        $response = $this->actingAs($this->user('9123456789'))
            ->postJson("/api/exams/{$exam->id}/attempts", ['student_id' => $child->id])
            ->assertOk();

        $this->assertArrayNotHasKey('correct_answer', $response->json('exam.questions.0'));

        $otherParent = User::query()->create(['name' => 'Other Parent', 'phone' => '9000000011']);
        $otherParent->assignSchoolRole($this->pilot->id, 'parent');

        $this->actingAs($otherParent)
            ->postJson("/api/exams/{$exam->id}/attempts", ['student_id' => $child->id])
            ->assertForbidden();

        $this->actingAs($this->otherAdmin)
            ->postJson("/api/exams/{$exam->id}/publish")
            ->assertForbidden();
    }

    public function test_smc_grievances_hidden_from_parents(): void
    {
        $parent = $this->user('9123456789');

        $this->actingAs($parent)
            ->postJson('/api/feedback', [
                'school_id' => $this->pilot->id,
                'category' => 'general',
                'subject' => 'Grievance',
                'body' => 'Please fix the wall.',
                'direction' => 'parent_to_smc',
            ])
            ->assertCreated();

        $this->assertSame([], $this->actingAs($parent)
            ->getJson('/api/smc/board?school_id='.$this->pilot->id)
            ->assertOk()
            ->json('grievances'));

        $this->assertCount(1, $this->actingAs($this->user('9847012345'))
            ->getJson('/api/smc/board?school_id='.$this->pilot->id)
            ->assertOk()
            ->json('grievances'));

        $thread = FeedbackThread::query()->where('direction', 'parent_to_smc')->firstOrFail();
        $this->actingAs($this->user('9847012345'))
            ->getJson("/api/feedback/{$thread->id}")
            ->assertOk();
    }

    public function test_calendar_and_smc_writes_are_scoped(): void
    {
        $this->actingAs($this->otherAdmin)
            ->postJson('/api/calendar', [
                'school_id' => $this->pilot->id,
                'title' => 'Fake holiday',
                'event_type' => 'holiday',
                'starts_at' => now()->addDays(3)->toIso8601String(),
            ])
            ->assertForbidden();

        $this->actingAs($this->otherAdmin)
            ->postJson('/api/smc/meetings', [
                'school_id' => $this->pilot->id,
                'title' => 'Fake meeting',
                'scheduled_at' => now()->addDays(3)->toIso8601String(),
            ])
            ->assertForbidden();
    }
}
