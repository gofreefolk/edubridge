<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_cannot_create_notices(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $parent = User::where('phone', '9123456789')->first();

        $this->actingAs($parent)
            ->postJson('/api/notices', [
                'school_id' => 1,
                'title' => 'Test',
                'body' => 'Body',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_notices(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $admin = User::where('phone', '9876543210')->first();

        $this->actingAs($admin)
            ->postJson('/api/notices', [
                'school_id' => 1,
                'title' => 'Test notice',
                'body' => 'Body text',
            ])
            ->assertCreated();
    }

    public function test_driver_cannot_access_parent_dashboard(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $driver = User::where('phone', '9111222333')->first();

        $this->actingAs($driver)
            ->getJson('/api/parent/dashboard?school_id=1')
            ->assertForbidden();
    }

    public function test_auth_me_includes_roles(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $teacher = User::where('phone', '9876501234')->first();

        $this->actingAs($teacher)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonStructure(['user' => ['roles', 'primary_role', 'teacher_class_id']]);
    }
}
