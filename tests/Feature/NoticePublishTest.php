<?php

namespace Tests\Feature;

use App\Jobs\SendUrgentNoticeWhatsApp;
use App\Models\Notice;
use App\Models\School;
use App\Models\User;
use App\Models\WhatsAppOptIn;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NoticePublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_urgent_notice_dispatches_whatsapp_job(): void
    {
        Queue::fake();

        $this->seed(PilotSchoolSeeder::class);

        $admin = User::where('phone', '9876543210')->first();
        $notice = Notice::where('priority', 'normal')->where('status', 'published')->first();

        $notice->update(['status' => 'draft', 'priority' => 'urgent', 'published_at' => null, 'magic_link_token' => null]);

        $this->actingAs($admin)
            ->postJson("/api/notices/{$notice->id}/publish")
            ->assertOk()
            ->assertJsonPath('notice.status', 'published');

        Queue::assertPushed(SendUrgentNoticeWhatsApp::class);
    }

    public function test_parent_dashboard_returns_pilot_data(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $parent = User::where('phone', '9123456789')->first();
        $school = School::where('code', 'STMS-LP')->first();

        $this->actingAs($parent)
            ->getJson("/api/parent/dashboard?school_id={$school->id}")
            ->assertOk()
            ->assertJsonPath('school.name', "St. Mary's LP School")
            ->assertJsonStructure(['children', 'stats', 'upcoming_events', 'recent_notices']);
    }
}
