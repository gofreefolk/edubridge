<?php

namespace App\Console\Commands;

use App\Services\Comms\EventReminderService;
use Illuminate\Console\Command;

class SendEventRemindersCommand extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Send due calendar event reminders via WhatsApp';

    public function handle(EventReminderService $service): int
    {
        $count = $service->sendDueReminders();
        $this->info("Sent {$count} event reminder batch(es).");

        return self::SUCCESS;
    }
}
