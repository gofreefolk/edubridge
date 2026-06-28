<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function schoolAdmin(): User
    {
        $this->seed(PilotSchoolSeeder::class);

        return User::query()->where('phone', '9876543210')->firstOrFail();
    }

    private function school(): School
    {
        return School::query()->where('code', 'STMS-LP')->firstOrFail();
    }

    public function test_school_admin_can_update_school_profile(): void
    {
        $admin = $this->schoolAdmin();
        $school = $this->school();

        $this->actingAs($admin)
            ->putJson('/api/admin/school', [
                'school_id' => $school->id,
                'name' => 'Updated School Name',
                'district' => 'Ernakulam',
                'whatsapp_bridge_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('school.name', 'Updated School Name')
            ->assertJsonPath('school.whatsapp_bridge_enabled', false);
    }

    public function test_school_admin_can_manage_classes_and_sections(): void
    {
        $admin = $this->schoolAdmin();
        $school = $this->school();

        $create = $this->actingAs($admin)
            ->postJson('/api/admin/classes', [
                'school_id' => $school->id,
                'name' => 'Class 10',
            ])
            ->assertCreated();

        $classId = $create->json('class.id');

        $section = $this->actingAs($admin)
            ->postJson("/api/admin/classes/{$classId}/sections", [
                'school_id' => $school->id,
                'name' => 'B',
            ])
            ->assertCreated()
            ->json('section.id');

        $this->actingAs($admin)
            ->putJson("/api/admin/sections/{$section}", [
                'school_id' => $school->id,
                'name' => 'C',
            ])
            ->assertOk()
            ->assertJsonPath('section.name', 'C');
    }

    public function test_school_admin_can_assign_staff_and_students(): void
    {
        $admin = $this->schoolAdmin();
        $school = $this->school();

        $this->actingAs($admin)
            ->postJson('/api/admin/staff', [
                'school_id' => $school->id,
                'name' => 'New Teacher',
                'phone' => '9000000011',
                'role' => 'teacher',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('school_user', [
            'school_id' => $school->id,
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $student = Student::query()->where('school_id', $school->id)->firstOrFail();

        $this->actingAs($admin)
            ->getJson("/api/admin/students?school_id={$school->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', fn ($total) => $total >= 1);

        $this->actingAs($admin)
            ->putJson("/api/admin/students/{$student->id}", [
                'school_id' => $school->id,
                'name' => 'Updated Student',
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('student.name', 'Updated Student');
    }

    public function test_school_admin_can_link_and_unlink_parents(): void
    {
        $admin = $this->schoolAdmin();
        $school = $this->school();
        $student = Student::query()->where('school_id', $school->id)->firstOrFail();

        $parent = $this->actingAs($admin)
            ->postJson("/api/admin/students/{$student->id}/parents", [
                'school_id' => $school->id,
                'name' => 'Extra Parent',
                'phone' => '9000000022',
                'relationship' => 'guardian',
                'is_primary' => false,
            ])
            ->assertCreated()
            ->json('parent');

        $this->assertDatabaseHas('parent_student', [
            'student_id' => $student->id,
            'parent_user_id' => $parent['id'],
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/students/{$student->id}/parents/{$parent['id']}", [
                'school_id' => $school->id,
            ])
            ->assertOk();

        $this->assertDatabaseMissing('parent_student', [
            'student_id' => $student->id,
            'parent_user_id' => $parent['id'],
        ]);
    }

    public function test_school_admin_can_create_co_admin_invite(): void
    {
        $admin = $this->schoolAdmin();
        $school = $this->school();

        $this->actingAs($admin)
            ->postJson('/api/admin/invites', [
                'school_id' => $school->id,
                'name' => 'Co Admin',
                'phone' => '9000000033',
            ])
            ->assertCreated()
            ->assertJsonStructure(['invite' => ['invite_url']]);

        $this->assertDatabaseHas('school_admin_invites', [
            'school_id' => $school->id,
            'phone' => '9000000033',
            'status' => 'pending',
        ]);
    }

    public function test_non_admin_cannot_access_school_management(): void
    {
        $this->seed(PilotSchoolSeeder::class);
        $parent = User::query()->where('phone', '9123456789')->firstOrFail();
        $school = $this->school();

        $this->actingAs($parent)
            ->putJson('/api/admin/school', [
                'school_id' => $school->id,
                'name' => 'Hacked',
            ])
            ->assertForbidden();
    }

    public function test_cannot_create_duplicate_class_name(): void
    {
        $admin = $this->schoolAdmin();
        $school = $this->school();
        $existing = SchoolClass::query()->where('school_id', $school->id)->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/admin/classes', [
                'school_id' => $school->id,
                'name' => $existing->name,
            ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => __('edubridge.class_name_duplicate', ['name' => $existing->name]),
            ]);
    }

    public function test_cannot_delete_class_with_students(): void
    {
        $admin = $this->schoolAdmin();
        $school = $this->school();
        $student = Student::query()->where('school_id', $school->id)->whereNotNull('school_class_id')->firstOrFail();
        $class = SchoolClass::query()->findOrFail($student->school_class_id);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/classes/{$class->id}", [
                'school_id' => $school->id,
            ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => __('edubridge.class_has_students'),
            ]);
    }
}
