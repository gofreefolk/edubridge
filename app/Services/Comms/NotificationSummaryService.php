<?php

namespace App\Services\Comms;

use App\Models\FeedbackThread;
use App\Models\Notice;
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
        $role = $user->hasAnyRole('super_admin')
            ? 'school_admin'
            : ($user->roleAtSchool($schoolId) ?? $user->primaryRole());

        $unreadNotices = 0;
        $urgentNotice = null;
        $openMessages = 0;
        $inboxMessages = 0;

        if (in_array($role, ['parent', 'grandparent'], true)) {
            $notices = $this->noticeService->forParent($user, $schoolId);
            $unreadNotices = $notices->filter(
                fn (Notice $notice) => ! $notice->reads()->where('user_id', $user->id)->exists()
            )->count();
            $urgent = $notices->firstWhere('priority', 'urgent');
            if ($urgent && ! $urgent->reads()->where('user_id', $user->id)->exists()) {
                $urgentNotice = [
                    'id' => $urgent->id,
                    'title' => $urgent->title,
                    'magic_link_token' => $urgent->magic_link_token,
                ];
            }
            $openMessages = FeedbackThread::query()
                ->where('school_id', $schoolId)
                ->where('created_by', $user->id)
                ->whereIn('status', ['open', 'acknowledged'])
                ->count();
        }

        if (in_array($role, ['school_admin', 'teacher'], true) || $user->hasAnyRole('super_admin')) {
            $inboxMessages = FeedbackThread::query()
                ->where('school_id', $schoolId)
                ->whereIn('status', ['open', 'acknowledged'])
                ->when($role === 'teacher' && ! $user->hasAnyRole('super_admin'), function ($q) use ($user) {
                    $q->where('direction', 'parent_to_teacher');
                })
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
