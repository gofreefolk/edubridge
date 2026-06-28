<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\EventReminder;
use App\Models\FeedbackMessage;
use App\Models\FeedbackThread;
use App\Models\NotificationLog;
use App\Models\School;
use App\Models\User;
use App\Models\WhatsAppOptIn;
use Database\Seeders\PilotSchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseACommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_create_and_view_feedback_thread(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $parent = User::where('phone', '9123456789')->firstOrFail();
        $school = School::where('code', 'STMS-LP')->firstOrFail();
        $student = $parent->children()->where('students.school_id', $school->id)->firstOrFail();

        $create = $this->actingAs($parent)
            ->postJson('/api/feedback', [
                'school_id' => $school->id,
                'student_id' => $student->id,
                'category' => 'general',
                'subject' => 'Bus timing',
                'body' => 'What time does the bus arrive?',
                'direction' => 'parent_to_teacher',
            ])
            ->assertCreated();

        $threadId = $create->json('thread.id');

        $this->actingAs($parent)
            ->getJson("/api/feedback/{$threadId}")
            ->assertOk()
            ->assertJsonPath('thread.subject', 'Bus timing')
            ->assertJsonPath('thread.messages.0.body', 'What time does the bus arrive?');
    }

    public function test_school_admin_can_reply_to_parent_thread(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $parent = User::where('phone', '9123456789')->firstOrFail();
        $admin = User::where('phone', '9876543210')->firstOrFail();
        $school = School::where('code', 'STMS-LP')->firstOrFail();

        $thread = FeedbackThread::query()->create([
            'school_id' => $school->id,
            'created_by' => $parent->id,
            'category' => 'general',
            'subject' => 'Fees question',
            'direction' => 'parent_to_admin',
            'status' => 'open',
        ]);

        FeedbackMessage::query()->create([
            'feedback_thread_id' => $thread->id,
            'author_id' => $parent->id,
            'body' => 'When is the last date?',
        ]);

        $this->actingAs($admin)
            ->postJson("/api/feedback/{$thread->id}/reply", [
                'body' => 'Please pay by Friday.',
            ])
            ->assertOk()
            ->assertJsonPath('thread.status', 'acknowledged')
            ->assertJsonCount(2, 'thread.messages');
    }

    public function test_notification_summary_for_parent(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $parent = User::where('phone', '9123456789')->firstOrFail();
        $school = School::where('code', 'STMS-LP')->firstOrFail();

        $this->actingAs($parent)
            ->getJson('/api/comms/summary?school_id='.$school->id)
            ->assertOk()
            ->assertJsonStructure(['unread_notices', 'open_messages', 'total_badge', 'whatsapp_opt_in']);
    }

    public function test_parent_can_update_whatsapp_opt_in(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $parent = User::where('phone', '9123456789')->firstOrFail();
        $school = School::where('code', 'STMS-LP')->firstOrFail();

        $this->actingAs($parent)
            ->putJson('/api/comms/whatsapp-opt-in', [
                'school_id' => $school->id,
                'opted_in' => true,
            ])
            ->assertOk()
            ->assertJsonPath('opted_in', true);

        $this->assertDatabaseHas('whatsapp_opt_ins', [
            'user_id' => $parent->id,
            'school_id' => $school->id,
            'opted_in' => true,
        ]);
    }

    public function test_school_admin_can_create_notice_with_attachment(): void
    {
        Storage::fake('public');
        $this->seed(PilotSchoolSeeder::class);

        $admin = User::where('phone', '9876543210')->firstOrFail();
        $school = School::where('code', 'STMS-LP')->firstOrFail();

        $file = UploadedFile::fake()->create('circular.pdf', 100, 'application/pdf');

        $this->actingAs($admin)
            ->post('/api/notices', [
                'school_id' => $school->id,
                'title' => 'PDF notice',
                'body' => 'See attachment',
                'audience_type' => 'whole_school',
                'attachments' => [$file],
            ])
            ->assertCreated()
            ->assertJsonPath('notice.attachments.0.filename', 'circular.pdf');

        $this->assertDatabaseCount('notice_attachments', 1);
    }

    public function test_event_reminder_command_sends_whatsapp_to_opted_in_parents(): void
    {
        $this->seed(PilotSchoolSeeder::class);

        $school = School::where('code', 'STMS-LP')->firstOrFail();
        $school->update(['whatsapp_bridge_enabled' => true]);
        $parent = User::where('phone', '9123456789')->firstOrFail();

        WhatsAppOptIn::query()->updateOrCreate(
            ['user_id' => $parent->id, 'school_id' => $school->id],
            ['opted_in' => true, 'opted_in_at' => now()],
        );

        $event = CalendarEvent::query()->create([
            'school_id' => $school->id,
            'author_id' => User::where('phone', '9876543210')->value('id'),
            'title' => 'PTA Meeting',
            'event_type' => 'meeting',
            'starts_at' => now()->addDay(),
            'all_day' => false,
            'audience_type' => 'whole_school',
        ]);

        EventReminder::query()->create([
            'calendar_event_id' => $event->id,
            'remind_at' => now()->subMinute(),
            'channel' => 'whatsapp',
        ]);

        $this->artisan('events:send-reminders')->assertSuccessful();

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $parent->id,
            'channel' => 'whatsapp',
            'type' => 'event_reminder',
            'status' => 'sent',
        ]);
    }
}
