<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_platform_dashboard(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $superAdmin = User::query()->where('phone', '9900000001')->firstOrFail();

        $response = $this->actingAs($superAdmin)->getJson('/api/platform/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'schools',
                    'active_schools',
                    'users',
                    'students',
                    'notices',
                    'published_notices',
                ],
                'recent_schools',
            ]);
    }

    public function test_non_super_admin_cannot_access_platform_dashboard(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $parent = User::query()->where('phone', '9123456789')->firstOrFail();

        $this->actingAs($parent)
            ->getJson('/api/platform/dashboard')
            ->assertForbidden();
    }

    public function test_platform_role_is_loaded_without_school_attachment(): void
    {
        $user = User::factory()->create(['phone' => '9900000099']);

        DB::table('school_user')->insert([
            'school_id' => null,
            'user_id' => $user->id,
            'role' => 'super_admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($user->fresh()->isSuperAdmin());
        $this->assertContains('super_admin', $user->fresh()->getRoles());
    }
}
