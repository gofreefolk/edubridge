<?php

namespace Tests\Feature;

use App\Models\SmcMeeting;
use App\Models\User;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmcManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_admin_can_schedule_meeting_and_save_minutes(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $admin = User::query()->where('phone', '9876543210')->firstOrFail();

        $create = $this->actingAs($admin)
            ->postJson('/api/smc/meetings', [
                'school_id' => 1,
                'title' => 'Monthly SMC',
                'agenda' => 'Fee discussion',
                'scheduled_at' => now()->addDays(3)->toIso8601String(),
            ])
            ->assertCreated();

        $meetingId = $create->json('meeting.id');

        $this->actingAs($admin)
            ->putJson("/api/smc/meetings/{$meetingId}/minutes", [
                'minutes' => 'Approved new library fund.',
                'status' => 'completed',
            ])
            ->assertOk()
            ->assertJsonPath('meeting.status', 'completed');

        $this->assertDatabaseHas('smc_meetings', [
            'id' => $meetingId,
            'status' => 'completed',
        ]);
    }
}
