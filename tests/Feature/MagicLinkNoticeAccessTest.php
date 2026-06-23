<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\School;
use App\Models\User;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MagicLinkNoticeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_magic_link_requires_authentication(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $notice = Notice::where('magic_link_token', 'demo123abc')->first();

        $this->getJson("/api/notices/magic/{$notice->magic_link_token}")
            ->assertUnauthorized();
    }

    public function test_parent_at_school_can_open_magic_link(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $parent = User::where('phone', '9123456789')->first();
        $notice = Notice::where('magic_link_token', 'demo123abc')->first();

        $this->actingAs($parent)
            ->getJson("/api/notices/magic/{$notice->magic_link_token}")
            ->assertOk()
            ->assertJsonPath('notice.title', $notice->title);
    }

    public function test_school_admin_cannot_open_parent_magic_link(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $admin = User::where('phone', '9876543210')->first();
        $notice = Notice::where('magic_link_token', 'demo123abc')->first();

        $this->actingAs($admin)
            ->getJson("/api/notices/magic/{$notice->magic_link_token}")
            ->assertForbidden();
    }

    public function test_admin_can_create_and_publish_notice(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $admin = User::where('phone', '9876543210')->first();
        $school = School::where('code', 'STMS-LP')->first();

        $response = $this->actingAs($admin)
            ->postJson('/api/notices', [
                'school_id' => $school->id,
                'title' => 'പരീക്ഷാ അറിയിപ്പ്',
                'body' => 'നാളെ പരീക്ഷ ഉണ്ട്.',
                'audience_type' => 'whole_school',
            ])
            ->assertCreated();

        $noticeId = $response->json('notice.id');

        $this->actingAs($admin)
            ->postJson("/api/notices/{$noticeId}/publish")
            ->assertOk()
            ->assertJsonPath('notice.status', 'published')
            ->assertJsonStructure(['notice' => ['magic_link_token']]);
    }
}
