<?php

namespace App\Jobs;

use App\Models\Notice;
use App\Models\WhatsAppOptIn;
use App\Services\Notice\NoticeService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendUrgentNoticeWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $noticeId,
    ) {}

    public function handle(WhatsAppService $whatsApp, NoticeService $noticeService): void
    {
        $notice = Notice::query()
            ->with('school')
            ->find($this->noticeId);

        if (! $notice || ! $notice->school?->whatsapp_bridge_enabled) {
            return;
        }

        if ($notice->status !== 'published' || ! $notice->magic_link_token) {
            return;
        }

        $magicUrl = url('/n/'.$notice->magic_link_token);
        $message = $whatsApp->buildUrgentNoticeMessage(
            $notice->school->name,
            $notice->title,
            $magicUrl,
        );

        $recipients = $noticeService->eligibleRecipientsQuery($notice)
            ->whereNotNull('phone')
            ->whereIn('users.id', WhatsAppOptIn::query()
                ->select('user_id')
                ->where('school_id', $notice->school_id)
                ->where('opted_in', true))
            ->get();

        $sent = 0;

        foreach ($recipients as $user) {
            // Opt-in already verified in the query above.
            if ($whatsApp->sendToUser($user, $message, 'urgent_notice', $notice->school_id, checkOptIn: false)) {
                $sent++;
            }
        }

        if ($sent > 0) {
            $notice->update(['whatsapp_sent_at' => now()]);
        }
    }
}
