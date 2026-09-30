<?php

namespace App\Services\Comms;

use App\Models\FeedbackThread;
use App\Models\User;
use App\Models\WhatsAppOptIn;
use App\Services\Notice\NoticeService;

class NotificationSummaryService
{
    public function __construct(
        private readonly NoticeService $noticeService,
    ) {}

    public function summary(User $user, int $schoolId): array
    {
        $isSuperAdmin = $user->isSuperAdmin();
        $roles = $isSuperAdmin ? ['school_admin'] : $user->rolesAtSchool($schoolId);

        $unreadNotices = 0;
        $urgentNotice = null;
        $openMessages = 0;
        $inboxMessages = 0;

        if (array_intersect($roles, ['parent', 'grandparent'])) {
            $notices = $this->noticeService->visibleTo($user, $schoolId);
            $unread = $notices->where('is_read', false);
            $unreadNotices = $unread->count();

            $urgent = $unread->firstWhere('priority', 'urgent');
            if ($urgent) {
                $urgentNotice = [
                    'id' => $urgent->id,
                    'title' => $urgent->title,
                    'magic_link_token' => $urgent->magic_link_token,
                ];
            }

            $openMessages = FeedbackThread::query()
                ->where('school_id', $schoolId)
                ->where(fn ($q) => $q->where('created_by', $user->id)->orWhere('assigned_to', $user->id))
                ->whereIn('status', ['open', 'acknowledged'])
                ->count();
        }

        $isAdmin = in_array('school_admin', $roles, true);

        if ($isAdmin || in_array('teacher', $roles, true)) {
            $inboxMessages = FeedbackThread::query()
                ->where('school_id', $schoolId)
                ->whereIn('status', ['open', 'acknowledged'])
                ->when(! $isAdmin, fn ($q) => $q->where('direction', 'parent_to_teacher'))
                ->count();
        }

        $whatsappOptIn = WhatsAppOptIn::query()
            ->where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->first();

        return [
            'unread_notices' => $unreadNotices,
            'urgent_notice' => $urgentNotice,
            'open_messages' => $openMessages,
            'inbox_messages' => $inboxMessages,
            'total_badge' => $unreadNotices + $openMessages + $inboxMessages,
            'whatsapp_opt_in' => $whatsappOptIn?->opted_in ?? false,
        ];
    }
}
