<?php

namespace App\Console\Commands;

use App\Jobs\SendFeeReminderWhatsApp;
use App\Models\FeeInvoice;
use App\Models\School;
use App\Services\Fees\FeeReminderSettings;
use Illuminate\Console\Command;

/**
 * One reminder a few days before the due date, then one every few days while overdue,
 * up to a cut-off. Timing is per school (Fee setup). Each invoice is claimed
 * (last_reminded_at) before its job is queued.
 */
class SendFeeRemindersCommand extends Command
{
    protected $signature = 'fees:send-reminders';

    protected $description = 'Queue WhatsApp reminders for upcoming and overdue fee invoices';

    public function handle(FeeReminderSettings $reminderSettings): int
    {
        $today = today();
        $count = 0;

        $schoolIds = FeeInvoice::query()
            ->whereIn('status', ['issued', 'partially_paid'])
            ->distinct()
            ->pluck('school_id');

        foreach (School::query()->whereIn('id', $schoolIds)->get() as $school) {
            $config = $reminderSettings->for($school);

            if (! $config['enabled']) {
                continue;
            }

            FeeInvoice::query()
                ->where('school_id', $school->id)
                ->whereIn('status', ['issued', 'partially_paid'])
                ->whereDate('due_on', '<=', $today->copy()->addDays($config['reminder_days_before']))
                ->whereDate('due_on', '>=', $today->copy()->subDays($config['overdue_stop_after_days']))
                ->where(function ($q) use ($today, $config) {
                    $q->whereNull('last_reminded_at')
                        ->orWhere(fn ($q) => $q->whereDate('due_on', '<', $today)
                            ->where('last_reminded_at', '<=', now()->subDays($config['overdue_every_days'])));
                })
                ->orderBy('id')
                ->each(function (FeeInvoice $invoice) use (&$count) {
                    $claimed = FeeInvoice::query()
                        ->whereKey($invoice->id)
                        ->where(fn ($q) => $q->whereNull('last_reminded_at')->orWhere('last_reminded_at', $invoice->last_reminded_at))
                        ->update(['last_reminded_at' => now()]);

                    if ($claimed) {
                        SendFeeReminderWhatsApp::dispatch($invoice->id);
                        $count++;
                    }
                });
        }

        $this->info("Queued {$count} fee reminder(s).");

        return self::SUCCESS;
    }
}
