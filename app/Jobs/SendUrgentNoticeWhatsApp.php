<?php

namespace App\Jobs;

use App\Models\Notice;
use App\Models\User;
use App\Models\WhatsAppOptIn;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendUrgentNoticeWhatsApp implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $noticeId,
    ) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $notice = Notice::query()
            ->with('school')
            ->find($this->noticeId);

        if (! $notice || ! $notice->school?->whatsapp_bridge_enabled) {
            return;
        }

        $magicUrl = url('/n/'.$notice->magic_link_token);
        $message = $whatsApp->buildUrgentNoticeMessage(
            $notice->school->name,
            $notice->title,
            $magicUrl,
        );

        $optedInUserIds = WhatsAppOptIn::query()
            ->where('school_id', $notice->school_id)
            ->where('opted_in', true)
            ->pluck('user_id');

        User::query()
            ->whereIn('id', $optedInUserIds)
            ->whereNotNull('phone')
            ->each(fn (User $user) => $whatsApp->sendToUser(
                $user,
                $message,
                'urgent_notice',
                $notice->school_id,
            ));
    }
}
