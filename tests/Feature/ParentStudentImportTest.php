<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ParentStudentImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_parents_from_csv(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $admin = User::where('phone', '9876543210')->first();
        $school = School::where('code', 'STMS-LP')->first();

        $csv = <<<CSV
student_name,admission_number,class,section,parent_name,parent_phone,relationship
Test Child,STMS-TEST-001,6,A,Test Parent,9000000099,father
CSV;

        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

        $response = $this->actingAs($admin)->postJson('/api/admin/import/parents', [
            'school_id' => $school->id,
            'file' => $file,
        ]);

        $response->assertOk()->assertJsonPath('imported', 1);

        $this->assertDatabaseHas('students', [
            'admission_number' => 'STMS-TEST-001',
            'name' => 'Test Child',
        ]);

        $parent = User::where('phone', '9000000099')->first();
        $student = Student::where('admission_number', 'STMS-TEST-001')->first();
        $this->assertNotNull($parent);
        $this->assertNotNull($student);
        $this->assertTrue($parent->children()->where('students.id', $student->id)->exists());
    }

    public function test_parent_cannot_import_csv(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $parent = User::where('phone', '9123456789')->first();
        $school = School::where('code', 'STMS-LP')->first();

        $csv = "student_name,admission_number,class,section,parent_name,parent_phone,relationship\nX,,1,A,P,9000000088,father\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

        $this->actingAs($parent)
            ->postJson('/api/admin/import/parents', [
                'school_id' => $school->id,
                'file' => $file,
            ])
            ->assertForbidden();
    }

    public function test_sample_csv_is_downloadable(): void
    {
        $this->get('/api/admin/import/sample.csv')
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
