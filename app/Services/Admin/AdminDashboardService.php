<?php

namespace App\Services\Admin;

use App\Models\AttendanceRecord;
use App\Models\CentreLog;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistTemplate;
use App\Models\FeedbackThread;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Notice;
use App\Models\NotificationLog;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Notice\NoticeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * One-screen summary of a school's day for the admin home page. Aggregates data the
 * modules already record; every query is scoped to the school. Cached briefly per
 * school and day, since every admin opening the app runs all of these aggregates.
 */
class AdminDashboardService
{
    private const RECENT_DAYS = 7;

    private const CACHE_SECONDS_TODAY = 60;

    private const CACHE_SECONDS_PAST = 600;

    public function __construct(
        private readonly NoticeService $notices,
    ) {}

    public function summary(School $school, ?Carbon $date = null, bool $fresh = false): array
    {
        $date = ($date ?? today())->copy()->startOfDay();
        $key = "admin-dashboard:{$school->id}:{$date->toDateString()}";

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember(
            $key,
            $date->isToday() ? self::CACHE_SECONDS_TODAY : self::CACHE_SECONDS_PAST,
            fn () => $this->build($school, $date),
        );
    }

    private function build(School $school, Carbon $date): array
    {
        return [
            'date' => $date->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'attendance' => $this->attendance($school, $date),
            'notices' => $this->notices($school, $date),
            'feedback' => $this->feedback($school, $date),
            'checklists' => $this->checklists($school, $date),
            'centre_logs' => $this->centreLogs($school, $date),
            'adoption' => $this->adoption($school),
            'delivery' => $this->delivery($school, $date),
            'fees' => $this->fees($school, $date),
        ];
    }

    /**
     * Null until the school issues its first invoice, so schools without fees see no tile.
     */
    private function fees(School $school, Carbon $date): ?array
    {
        if (! FeeInvoice::query()->where('school_id', $school->id)->exists()) {
            return null;
        }

        $overdue = FeeInvoice::query()
            ->where('school_id', $school->id)
            ->whereIn('status', ['issued', 'partially_paid'])
            ->whereDate('due_on', '<', $date->toDateString())
            ->selectRaw('count(*) as invoices, coalesce(sum(total_paise - discount_paise - paid_paise), 0) as balance')
            ->first();

        return [
            'collected_paise' => (int) FeePayment::query()
                ->where('school_id', $school->id)
                ->whereNull('voided_at')
                ->whereBetween('paid_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                ->sum('amount_paise'),
            'overdue_invoices' => (int) $overdue->invoices,
            'overdue_paise' => (int) $overdue->balance,
        ];
    }

    private function attendance(School $school, Carbon $date): array
    {
        $students = Student::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->get(['id', 'school_class_id']);

        $records = AttendanceRecord::query()
            ->where('school_id', $school->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->whereDate('date', $date->toDateString())
            ->get(['student_id', 'status', 'absence_alert_sent_at']);

        $counts = collect(['present', 'late', 'absent', 'excused'])
            ->mapWithKeys(fn ($status) => [$status => $records->where('status', $status)->count()]);
        $marked = $records->count();

        // Classes with active students but nobody marked today.
        $markedStudentIds = $records->pluck('student_id')->flip();
        $unmarkedClassIds = $students
            ->groupBy('school_class_id')
            ->filter(fn ($group, $classId) => $classId && $group->every(fn ($s) => ! $markedStudentIds->has($s->id)))
            ->keys();

        return [
            'students' => $students->count(),
            'marked' => $marked,
            ...$counts->all(),
            'percent' => $marked > 0 ? round(($counts['present'] + $counts['late']) / $marked * 100, 1) : null,
            'alerts_sent' => $records->whereNotNull('absence_alert_sent_at')->count(),
            'unmarked_classes' => SchoolClass::query()
                ->whereIn('id', $unmarkedClassIds)
                ->orderBy('id')
                ->pluck('name')
                ->values(),
        ];
    }

    private function notices(School $school, Carbon $date): array
    {
        $recent = Notice::query()
            ->where('school_id', $school->id)
            ->where('status', 'published')
            ->where('published_at', '>=', $date->copy()->subDays(self::RECENT_DAYS - 1))
            ->where('published_at', '<', $date->copy()->addDay())
            ->orderByDesc('published_at')
            ->limit(10)
            ->get(['id', 'school_id', 'title', 'priority', 'audience_type', 'published_at']);

        $rows = $recent->map(fn (Notice $n) => [
            'id' => $n->id,
            'title' => $n->title,
            'priority' => $n->priority,
            'published_at' => $n->published_at?->toIso8601String(),
            ...$this->notices->analyticsCounts($n),
        ]);

        $eligible = $rows->sum('eligible_count');

        return [
            'published_recent' => $rows->count(),
            'read_percent' => $eligible > 0 ? round($rows->sum('read_count') / $eligible * 100, 1) : null,
            'scheduled' => Notice::query()
                ->where('school_id', $school->id)
                ->where('status', 'draft')
                ->where('scheduled_publish_at', '>', now())
                ->count(),
            'recent' => $rows->take(5)->values(),
        ];
    }

    private function feedback(School $school, Carbon $date): array
    {
        $open = FeedbackThread::query()
            ->where('school_id', $school->id)
            ->whereIn('status', ['open', 'acknowledged'])
            ->orderBy('created_at')
            ->get(['id', 'subject', 'status', 'category', 'created_at']);

        $oldest = $open->first();

        return [
            'open' => $open->where('status', 'open')->count(),
            'acknowledged' => $open->where('status', 'acknowledged')->count(),
            'oldest_open_days' => $oldest ? (int) $oldest->created_at->copy()->startOfDay()->diffInDays($date) : null,
            'oldest' => $open->take(3)->map(fn (FeedbackThread $t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'status' => $t->status,
                'created_at' => $t->created_at->toIso8601String(),
            ])->values(),
        ];
    }

    private function checklists(School $school, Carbon $date): array
    {
        $templates = ChecklistTemplate::query()
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->get(['id', 'name', 'frequency']);

        $periods = $templates->mapWithKeys(fn ($t) => [$t->id => $t->periodFor($date)->toDateString()]);

        // Range + filter rather than whereIn on dates: SQLite stores a time part.
        $submitted = ChecklistSubmission::query()
            ->whereIn('checklist_template_id', $templates->pluck('id'))
            ->whereDate('period_date', '>=', $periods->min() ?? $date->toDateString())
            ->whereDate('period_date', '<=', $periods->max() ?? $date->toDateString())
            ->get(['checklist_template_id', 'period_date'])
            ->filter(fn ($s) => $s->period_date->toDateString() === $periods->get($s->checklist_template_id))
            ->pluck('checklist_template_id')
            ->flip();

        $pending = $templates->reject(fn ($t) => $submitted->has($t->id));

        return [
            'total' => $templates->count(),
            'done' => $templates->count() - $pending->count(),
            'pending' => $pending->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'frequency' => $t->frequency])->values(),
        ];
    }

    private function centreLogs(School $school, Carbon $date): array
    {
        $counts = CentreLog::query()
            ->where('school_id', $school->id)
            ->where('occurred_at', '>=', $date->copy()->subDays(self::RECENT_DAYS - 1))
            ->where('occurred_at', '<', $date->copy()->addDay())
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return [
            'incidents_recent' => (int) ($counts['incident'] ?? 0),
            'health_recent' => (int) ($counts['health'] ?? 0),
            'total_recent' => (int) $counts->sum(),
        ];
    }

    /**
     * Pilot adoption metric: share of linked parents who have read at least one notice.
     * Also how many have ever opened the app, and how many did in the last 7 days.
     */
    private function adoption(School $school): array
    {
        $parentIds = DB::table('school_user')
            ->where('school_id', $school->id)
            ->whereIn('role', ['parent', 'grandparent'])
            ->where('is_active', true)
            ->distinct()
            ->pluck('user_id');

        $readers = DB::table('notice_reads')
            ->join('notices', 'notices.id', '=', 'notice_reads.notice_id')
            ->where('notices.school_id', $school->id)
            ->whereIn('notice_reads.user_id', $parentIds)
            ->distinct()
            ->count('notice_reads.user_id');

        $seen = DB::table('users')
            ->whereIn('id', $parentIds)
            ->whereNotNull('last_seen_at')
            ->selectRaw('count(*) as ever, sum(case when last_seen_at >= ? then 1 else 0 end) as recent', [now()->subDays(self::RECENT_DAYS)])
            ->first();

        return [
            'parents' => $parentIds->count(),
            'readers' => $readers,
            'seen' => (int) $seen->ever,
            'active_recent' => (int) $seen->recent,
            'percent' => $parentIds->count() > 0 ? round($readers / $parentIds->count() * 100, 1) : null,
        ];
    }

    /**
     * WhatsApp / SMS delivery over the last 24 hours, so a broken gateway shows up.
     */
    private function delivery(School $school, Carbon $date): array
    {
        $since = $date->isToday() ? now()->subDay() : $date->copy()->subDay();
        $until = $date->isToday() ? now() : $date->copy()->addDay();

        $rows = NotificationLog::query()
            ->where('school_id', $school->id)
            ->whereBetween('created_at', [$since, $until])
            ->selectRaw('channel, status, count(*) as total')
            ->groupBy('channel', 'status')
            ->get();

        return [
            'sent' => (int) $rows->where('status', 'sent')->sum('total'),
            'failed' => (int) $rows->where('status', 'failed')->sum('total'),
            'pending' => (int) $rows->where('status', 'pending')->sum('total'),
        ];
    }
}
