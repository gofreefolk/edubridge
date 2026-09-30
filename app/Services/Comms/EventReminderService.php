<?php

namespace App\Services\Comms;

use App\Jobs\SendEventReminderWhatsApp;
use App\Models\EventReminder;
use App\Models\User;
use App\Models\WhatsAppOptIn;
use App\Services\WhatsApp\WhatsAppService;

class EventReminderService
{
    public function __construct(
        private readonly WhatsAppService $whatsApp,
    ) {}

    /**
     * Claims each due reminder (sets sent_at) before queueing its delivery, so an
     * overlapping scheduler run can never pick up the same reminder twice.
     *
     * @return int number of reminders queued
     */
    public function sendDueReminders(): int
    {
        $count = 0;

        EventReminder::query()
            ->whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->orderBy('remind_at')
            ->pluck('id')
            ->each(function (int $id) use (&$count) {
                $claimed = EventReminder::query()
                    ->whereKey($id)
                    ->whereNull('sent_at')
                    ->update(['sent_at' => now()]);

                if ($claimed === 1) {
                    SendEventReminderWhatsApp::dispatch($id);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @return int messages sent
     */
    public function deliver(EventReminder $reminder): int
    {
        $event = $reminder->calendarEvent;
        $school = $event?->school;

        if (! $event || ! $school || ! $school->whatsapp_bridge_enabled || ! config('edubridge.whatsapp.enabled')) {
            return 0;
        }

        // Reminders for events that have already started are stale; skip them.
        if ($event->starts_at->isPast()) {
            return 0;
        }

        $title = $event->title_en ?: $event->title;
        $date = $event->starts_at->timezone(config('app.timezone'))->format('d M Y');
        $message = "{$school->name}: Reminder — {$title} on {$date}.";

        $sent = 0;

        User::query()
            ->whereIn('id', WhatsAppOptIn::query()
                ->select('user_id')
                ->where('school_id', $school->id)
                ->where('opted_in', true))
            ->whereNotNull('phone')
            ->each(function (User $user) use ($message, $school, &$sent) {
                if ($this->whatsApp->sendToUser($user, $message, 'event_reminder', $school->id, checkOptIn: false)) {
                    $sent++;
                }
            });

        return $sent;
    }
}
