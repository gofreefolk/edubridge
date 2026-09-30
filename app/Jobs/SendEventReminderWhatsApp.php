<?php

namespace App\Jobs;

use App\Models\EventReminder;
use App\Services\Comms\EventReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendEventReminderWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $reminderId,
    ) {}

    public function handle(EventReminderService $service): void
    {
        $reminder = EventReminder::query()
            ->with('calendarEvent.school')
            ->find($this->reminderId);

        if ($reminder) {
            $service->deliver($reminder);
        }
    }
}
