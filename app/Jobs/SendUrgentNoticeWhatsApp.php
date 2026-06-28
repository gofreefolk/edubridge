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

        $recipientIds = $noticeService->eligibleRecipientUsers($notice)->pluck('id');

        $recipients = $noticeService->eligibleRecipientUsers($notice)->keyBy('id');

        $optedInUserIds = WhatsAppOptIn::query()
            ->where('school_id', $notice->school_id)
            ->where('opted_in', true)
            ->whereIn('user_id', $recipients->keys())
            ->pluck('user_id');

        $sent = 0;

        foreach ($optedInUserIds as $userId) {
            $user = $recipients->get($userId);
            if (! $user?->phone) {
                continue;
            }

            $whatsApp->sendToUser($user, $message, 'urgent_notice', $notice->school_id);
            $sent++;
        }

        if ($sent > 0) {
            $notice->update(['whatsapp_sent_at' => now()]);
        }
    }
}
