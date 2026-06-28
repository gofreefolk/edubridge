<?php

namespace App\Console\Commands;

use App\Services\Notice\NoticeService;
use Illuminate\Console\Command;

class PublishScheduledNotices extends Command
{
    protected $signature = 'notices:publish-scheduled';

    protected $description = 'Publish notices whose scheduled time has arrived';

    public function handle(NoticeService $noticeService): int
    {
        $count = $noticeService->publishDueScheduled();

        if ($count > 0) {
            $this->info("Published {$count} scheduled notice(s).");
        }

        return self::SUCCESS;
    }
}
