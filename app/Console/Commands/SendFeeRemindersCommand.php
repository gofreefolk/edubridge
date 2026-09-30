<?php

namespace App\Console\Commands;

use App\Jobs\SendFeeReminderWhatsApp;
use App\Models\FeeInvoice;
use Illuminate\Console\Command;

/**
 * One reminder a few days before the due date, then one a week while overdue, up to a
 * cut-off. Each invoice is claimed (last_reminded_at) before its job is queued.
 */
class SendFeeRemindersCommand extends Command
{
    protected $signature = 'fees:send-reminders';

    protected $description = 'Queue WhatsApp reminders for upcoming and overdue fee invoices';

    public function handle(): int
    {
        $config = config('edubridge.fees');
        $today = today();
        $count = 0;

        FeeInvoice::query()
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

        $this->info("Queued {$count} fee reminder(s).");

        return self::SUCCESS;
    }
}
