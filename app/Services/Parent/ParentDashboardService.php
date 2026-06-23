<?php

namespace App\Services\Parent;

use App\Models\CalendarEvent;
use App\Models\FeedbackThread;
use App\Models\Notice;
use App\Models\Student;
use App\Models\User;
use App\Services\Notice\NoticeService;

class ParentDashboardService
{
    public function __construct(
        private readonly NoticeService $noticeService,
    ) {}

    public function dashboard(User $user, int $schoolId, ?int $studentId = null): array
    {
        $children = $user->children()
            ->where('school_id', $schoolId)
            ->with(['schoolClass:id,name', 'section:id,name'])
            ->get();

        $activeStudent = $studentId
            ? $children->firstWhere('id', $studentId)
            : $children->first();

        $notices = $this->noticeService->forParent(
            $user,
            $schoolId,
            $activeStudent?->id,
        );

        $urgentNotice = $notices->firstWhere('priority', 'urgent');

        $upcomingEvents = CalendarEvent::query()
            ->where('school_id', $schoolId)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(5)
            ->get();

        $openFeedbackCount = FeedbackThread::query()
            ->where('school_id', $schoolId)
            ->where('created_by', $user->id)
            ->whereIn('status', ['open', 'acknowledged'])
            ->count();

        $unreadNotices = $notices->filter(function (Notice $notice) use ($user) {
            return ! $notice->reads()->where('user_id', $user->id)->exists();
        })->count();

        return [
            'school' => $user->schools()->where('schools.id', $schoolId)->first(['schools.id', 'schools.name']),
            'children' => $children->map(fn (Student $s) => $this->studentPayload($s)),
            'active_student' => $activeStudent ? $this->studentPayload($activeStudent) : null,
            'urgent_notice' => $urgentNotice ? $this->noticePayload($urgentNotice) : null,
            'stats' => [
                'new_notices' => $unreadNotices,
                'replies_waiting' => $openFeedbackCount,
            ],
            'upcoming_events' => $upcomingEvents->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'title_en' => $e->title_en,
                'event_type' => $e->event_type,
                'starts_at' => $e->starts_at->toIso8601String(),
                'all_day' => $e->all_day,
            ]),
            'recent_notices' => $notices->take(10)->map(fn ($n) => $this->noticePayload($n)),
        ];
    }

    private function studentPayload(Student $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'class' => $student->schoolClass?->name,
            'section' => $student->section?->name,
            'status' => $student->status,
        ];
    }

    private function noticePayload(Notice $notice): array
    {
        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'title_en' => $notice->title_en,
            'priority' => $notice->priority,
            'published_at' => $notice->published_at?->toIso8601String(),
            'magic_link_token' => $notice->magic_link_token,
            'is_pinned' => $notice->pinned_until?->isFuture() ?? false,
        ];
    }
}
