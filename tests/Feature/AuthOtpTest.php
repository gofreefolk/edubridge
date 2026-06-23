<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Notice;
use App\Models\PhoneOtp;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_magic_link_requires_parent_at_school(): void
    {
        $school = School::create([
            'name' => 'Test School',
            'code' => 'TEST-01',
        ]);

        $author = User::create([
            'name' => 'Admin',
            'phone' => '9000000001',
        ]);

        $parent = User::create([
            'name' => 'Parent',
            'phone' => '9123456789',
        ]);

        $school->users()->attach($parent->id, ['role' => 'parent', 'is_active' => true]);

        $year = AcademicYear::create([
            'school_id' => $school->id,
            'name' => '2025-26',
            'starts_on' => '2025-06-01',
            'ends_on' => '2026-03-31',
            'is_current' => true,
        ]);

        $class = SchoolClass::create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'name' => '3',
        ]);

        $section = Section::create([
            'school_class_id' => $class->id,
            'name' => 'A',
        ]);

        $student = Student::create([
            'school_id' => $school->id,
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'name' => 'Child',
            'admission_number' => '001',
        ]);

        $parent->children()->attach($student->id, ['relationship' => 'father', 'is_primary' => true]);

        Notice::create([
            'school_id' => $school->id,
            'author_id' => $author->id,
            'title' => 'Test notice',
            'body' => 'Body',
            'priority' => 'urgent',
            'audience_type' => 'whole_school',
            'status' => 'published',
            'published_at' => now(),
            'magic_link_token' => 'testtoken123',
        ]);

        $this->getJson('/api/notices/magic/testtoken123')->assertUnauthorized();

        $this->actingAs($parent)
            ->getJson('/api/notices/magic/testtoken123')
            ->assertOk()
            ->assertJsonPath('notice.title', 'Test notice')
            ->assertJsonPath('notice.school.name', 'Test School');
    }

    public function test_otp_verify_logs_user_in(): void
    {
        PhoneOtp::create([
            'phone' => '9123456789',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/otp/verify', [
            'phone' => '9123456789',
            'code' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.phone', '9123456789');

        $this->assertAuthenticated();
    }
}
