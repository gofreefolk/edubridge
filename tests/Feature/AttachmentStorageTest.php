<?php

namespace Tests\Feature;

use App\Models\NoticeAttachment;
use App\Models\School;
use App\Models\User;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_notice_attachment_uses_public_disk_by_default(): void
    {
        Storage::fake('public');
        $this->seed(PilotSchoolSeeder::class);

        $admin = User::where('phone', '9876543210')->firstOrFail();
        $school = School::where('code', 'STMS-LP')->firstOrFail();
        $file = UploadedFile::fake()->create('circular.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)
            ->post('/api/notices', [
                'school_id' => $school->id,
                'title' => 'PDF notice',
                'body' => 'See attachment',
                'audience_type' => 'whole_school',
                'attachments' => [$file],
            ])
            ->assertCreated();

        $attachment = NoticeAttachment::query()->firstOrFail();

        $this->assertSame('public', $attachment->disk);
        Storage::disk('public')->assertExists($attachment->path);
        $this->assertNotEmpty($response->json('notice.attachments.0.url'));
        $this->assertSame('circular.pdf', $response->json('notice.attachments.0.filename'));
    }

    public function test_notice_attachment_can_store_on_s3_disk(): void
    {
        Storage::fake('s3');
        config([
            'edubridge.attachments.disk' => 's3',
            'edubridge.attachments.visibility' => 'public',
        ]);

        $this->seed(PilotSchoolSeeder::class);

        $admin = User::where('phone', '9876543210')->firstOrFail();
        $school = School::where('code', 'STMS-LP')->firstOrFail();
        $file = UploadedFile::fake()->create('holiday.pdf', 50, 'application/pdf');

        $this->actingAs($admin)
            ->post('/api/notices', [
                'school_id' => $school->id,
                'title' => 'Holiday notice',
                'body' => 'See attachment',
                'audience_type' => 'whole_school',
                'attachments' => [$file],
            ])
            ->assertCreated()
            ->assertJsonPath('notice.attachments.0.filename', 'holiday.pdf');

        $attachment = NoticeAttachment::query()->firstOrFail();

        $this->assertSame('s3', $attachment->disk);
        Storage::disk('s3')->assertExists($attachment->path);
        $this->assertNotEmpty($attachment->url());
    }
}
