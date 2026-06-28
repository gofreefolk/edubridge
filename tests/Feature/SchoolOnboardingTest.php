<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolAdminInvite;
use App\Models\User;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_submit_school_registration(): void
    {
        $response = $this->postJson('/api/schools/register', [
            'name' => 'New LP School',
            'code' => 'NEW-LP',
            'district' => 'Thrissur',
            'type' => 'government',
            'admin_name' => 'Head Master',
            'admin_phone' => '9000000002',
        ]);

        $response->assertCreated()
            ->assertJsonPath('school.approval_status', School::APPROVAL_PENDING);

        $this->assertDatabaseHas('schools', [
            'code' => 'NEW-LP',
            'approval_status' => School::APPROVAL_PENDING,
            'is_active' => false,
        ]);
    }

    public function test_super_admin_can_create_school_with_invite(): void
    {
        $this->seed(PilotSchoolSeeder::class);
        $superAdmin = User::query()->where('phone', '9900000001')->firstOrFail();

        $response = $this->actingAs($superAdmin)->postJson('/api/platform/schools', [
            'name' => 'Govt HS School',
            'code' => 'GOVT-HS',
            'district' => 'Kozhikode',
            'type' => 'government',
            'admin_name' => 'Principal',
            'admin_phone' => '9000000003',
        ]);

        $response->assertCreated()
            ->assertJsonPath('school.approval_status', School::APPROVAL_APPROVED)
            ->assertJsonStructure(['invite' => ['invite_url']]);

        $this->assertDatabaseHas('school_admin_invites', [
            'phone' => '9000000003',
            'status' => SchoolAdminInvite::STATUS_PENDING,
        ]);
    }

    public function test_super_admin_can_approve_pending_registration(): void
    {
        $this->seed(PilotSchoolSeeder::class);
        $superAdmin = User::query()->where('phone', '9900000001')->firstOrFail();

        $school = School::query()->create([
            'name' => 'Pending School',
            'code' => 'PEND-01',
            'district' => 'Ernakulam',
            'type' => 'aided',
            'is_active' => false,
            'approval_status' => School::APPROVAL_PENDING,
            'admin_contact_name' => 'Admin Person',
            'admin_contact_phone' => '9000000004',
        ]);

        $response = $this->actingAs($superAdmin)->postJson("/api/platform/schools/{$school->id}/approve");

        $response->assertOk()
            ->assertJsonPath('school.approval_status', School::APPROVAL_APPROVED);

        $this->assertDatabaseHas('school_admin_invites', [
            'school_id' => $school->id,
            'phone' => '9000000004',
        ]);
    }

    public function test_pending_invite_is_auto_accepted_on_login(): void
    {
        $school = School::query()->create([
            'name' => 'Auto School',
            'code' => 'AUTO-01',
            'district' => 'Ernakulam',
            'type' => 'aided',
            'is_active' => true,
            'approval_status' => School::APPROVAL_APPROVED,
        ]);

        SchoolAdminInvite::query()->create([
            'school_id' => $school->id,
            'phone' => '9496330999',
            'name' => 'Pending Admin',
            'token' => 'autoaccepttoken123',
            'status' => SchoolAdminInvite::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $user = User::factory()->create(['phone' => '9496330999']);

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk();

        $this->assertTrue($user->fresh()->hasAnyRole('school_admin'));
    }

    public function test_admin_can_accept_invite_via_link(): void
    {
        $school = School::query()->create([
            'name' => 'Invite School',
            'code' => 'INV-01',
            'district' => 'Ernakulam',
            'type' => 'aided',
            'is_active' => true,
            'approval_status' => School::APPROVAL_APPROVED,
        ]);

        $invite = SchoolAdminInvite::query()->create([
            'school_id' => $school->id,
            'phone' => '9000000005',
            'name' => 'Invite Admin',
            'token' => 'testinvitetoken123',
            'status' => SchoolAdminInvite::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $user = User::factory()->create(['phone' => '9000000005']);

        $this->actingAs($user)
            ->postJson("/api/invites/admin/{$invite->token}/accept")
            ->assertOk();

        $this->assertTrue($user->fresh()->hasAnyRole('school_admin'));
    }
}
