<?php

namespace App\Services\Comms;

use App\Models\EventReminder;
use App\Models\User;
use App\Models\WhatsAppOptIn;
use App\Services\WhatsApp\WhatsAppService;

class EventReminderService
{
    public function __construct(
        private readonly WhatsAppService $whatsApp,
    ) {}

    public function sendDueReminders(): int
    {
        $count = 0;

        EventReminder::query()
            ->whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->with(['calendarEvent.school'])
            ->orderBy('remind_at')
            ->each(function (EventReminder $reminder) use (&$count) {
                if ($this->dispatchReminder($reminder)) {
                    $count++;
                }
            });

        return $count;
    }

    private function dispatchReminder(EventReminder $reminder): bool
    {
        $event = $reminder->calendarEvent;
        $school = $event?->school;

        if (! $event || ! $school || ! $school->whatsapp_bridge_enabled) {
            $reminder->update(['sent_at' => now()]);

            return false;
        }

        if (! config('edubridge.whatsapp.enabled')) {
            $reminder->update(['sent_at' => now()]);

            return false;
        }

        $title = $event->title_en ?: $event->title;
        $date = $event->starts_at->timezone(config('app.timezone'))->format('d M Y');
        $message = "{$school->name}: Reminder — {$title} on {$date}.";

        $userIds = WhatsAppOptIn::query()
            ->where('school_id', $school->id)
            ->where('opted_in', true)
            ->pluck('user_id');

        $sent = 0;

        User::query()
            ->whereIn('id', $userIds)
            ->whereNotNull('phone')
            ->each(function (User $user) use ($message, $school, &$sent) {
                if ($this->whatsApp->sendToUser($user, $message, 'event_reminder', $school->id)) {
                    $sent++;
                }
            });

        $reminder->update(['sent_at' => now()]);

        return $sent > 0;
    }
}
