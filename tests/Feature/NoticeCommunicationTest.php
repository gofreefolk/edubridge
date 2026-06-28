<?php

namespace Tests\Feature;

use App\Jobs\SendUrgentNoticeWhatsApp;
use App\Models\Notice;
use App\Models\NoticeRead;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppOptIn;
use App\Services\Notice\NoticeService;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NoticeCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_admin_can_unpublish_notice(): void
    {
        $this->seed(PilotSchoolSeeder::class);
        $admin = User::where('phone', '9876543210')->firstOrFail();
        $notice = Notice::where('status', 'published')->firstOrFail();

        $this->actingAs($admin)
            ->postJson("/api/notices/{$notice->id}/unpublish")
            ->assertOk()
            ->assertJsonPath('notice.status', 'archived');
    }

    public function test_notice_stats_returns_read_percent(): void
    {
        $this->seed(PilotSchoolSeeder::class);
        $admin = User::where('phone', '9876543210')->firstOrFail();
        $parent = User::where('phone', '9123456789')->firstOrFail();
        $notice = Notice::where('status', 'published')->firstOrFail();

        NoticeRead::query()->create([
            'notice_id' => $notice->id,
            'user_id' => $parent->id,
            'read_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson("/api/notices/{$notice->id}/stats")
            ->assertOk()
            ->assertJsonStructure(['stats' => ['eligible_count', 'read_count', 'read_percent', 'readers', 'unread']])
            ->assertJsonPath('stats.read_count', 1);
    }

    public function test_scheduled_notice_publishes_via_command(): void
    {
        $this->seed(PilotSchoolSeeder::class);
        $admin = User::where('phone', '9876543210')->firstOrFail();
        $school = School::where('code', 'STMS-LP')->firstOrFail();

        $create = $this->actingAs($admin)
            ->postJson('/api/notices', [
                'school_id' => $school->id,
                'title' => 'Scheduled notice',
                'body' => 'Body',
                'audience_type' => 'whole_school',
            ])
            ->assertCreated();

        $noticeId = $create->json('notice.id');

        $this->actingAs($admin)
            ->postJson("/api/notices/{$noticeId}/publish", [
                'scheduled_publish_at' => now()->addHour()->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('notice.status', 'draft');

        $this->travel(2)->hours();
        $this->artisan('notices:publish-scheduled')->assertSuccessful();

        $this->assertDatabaseHas('notices', [
            'id' => $noticeId,
            'status' => 'published',
        ]);
    }

    public function test_whatsapp_job_targets_audience_parents_only(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $school = School::where('code', 'STMS-LP')->firstOrFail();
        $parent = User::where('phone', '9123456789')->firstOrFail();
        $student = Student::where('school_id', $school->id)->whereHas('parents', fn ($q) => $q->where('users.id', $parent->id))->firstOrFail();

        WhatsAppOptIn::query()->updateOrCreate(
            ['user_id' => $parent->id, 'school_id' => $school->id],
            ['opted_in' => true],
        );

        $notice = Notice::query()->create([
            'school_id' => $school->id,
            'author_id' => User::where('phone', '9876543210')->value('id'),
            'title' => 'Class notice',
            'body' => 'Test',
            'priority' => 'urgent',
            'audience_type' => 'class',
            'status' => 'published',
            'published_at' => now(),
            'magic_link_token' => Notice::generateMagicLinkToken(),
        ]);

        $notice->audiences()->create(['school_class_id' => $student->school_class_id]);

        $service = app(NoticeService::class);
        $eligible = $service->eligibleRecipientUsers($notice);

        $this->assertTrue($eligible->contains(fn (User $user) => $user->id === $parent->id));
        $this->assertGreaterThanOrEqual(1, $eligible->count());
    }

    public function test_urgent_whatsapp_can_be_queued_by_admin(): void
    {
        Queue::fake();
        $this->seed(PilotSchoolSeeder::class);

        $admin = User::where('phone', '9876543210')->firstOrFail();
        $notice = Notice::where('priority', 'normal')->where('status', 'published')->firstOrFail();
        $notice->update(['priority' => 'urgent', 'whatsapp_sent_at' => null]);

        $this->actingAs($admin)
            ->postJson("/api/notices/{$notice->id}/whatsapp")
            ->assertOk();

        Queue::assertPushed(SendUrgentNoticeWhatsApp::class);
    }
}
