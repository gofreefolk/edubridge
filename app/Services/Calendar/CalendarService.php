<?php

namespace App\Services\Calendar;

use App\Models\CalendarEvent;
use App\Models\EventReminder;
use App\Models\School;
use Illuminate\Support\Carbon;

class CalendarService
{
    public function listForSchool(int $schoolId, ?Carbon $from = null, ?Carbon $to = null)
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->addMonths(2)->endOfMonth();

        return CalendarEvent::query()
            ->where('school_id', $schoolId)
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get();
    }

    public function create(School $school, int $authorId, array $data): CalendarEvent
    {
        $event = CalendarEvent::query()->create([
            'school_id' => $school->id,
            'author_id' => $authorId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'title_en' => $data['title_en'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'event_type' => $data['event_type'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'all_day' => $data['all_day'] ?? false,
            'audience_type' => $data['audience_type'] ?? 'whole_school',
            'school_class_id' => $data['school_class_id'] ?? null,
            'section_id' => $data['section_id'] ?? null,
        ]);

        if ($data['remind_one_day_before'] ?? true) {
            EventReminder::query()->create([
                'calendar_event_id' => $event->id,
                'remind_at' => Carbon::parse($data['starts_at'])->subDay(),
                'channel' => 'whatsapp',
            ]);
        }

        return $event;
    }

    public function seedKeralaHolidays(School $school, int $year): void
    {
        $holidays = [
            ['title' => 'ഓണം', 'title_en' => 'Onam', 'month' => 9, 'day' => 5, 'type' => 'holiday'],
            ['title' => 'വിഷു', 'title_en' => 'Vishu', 'month' => 4, 'day' => 14, 'type' => 'holiday'],
            ['title' => 'ക്രിസ്തുമസ്', 'title_en' => 'Christmas', 'month' => 12, 'day' => 25, 'type' => 'holiday'],
            ['title' => 'ഗണേഷ് ചതുർത്ഥി', 'title_en' => 'Ganesh Chaturthi', 'month' => 8, 'day' => 27, 'type' => 'holiday'],
            ['title' => 'സ്വാതന്ത്ര്യദിനം', 'title_en' => 'Independence Day', 'month' => 8, 'day' => 15, 'type' => 'holiday'],
            ['title' => 'റിപ്പബ്ലിക് ദിനം', 'title_en' => 'Republic Day', 'month' => 1, 'day' => 26, 'type' => 'holiday'],
        ];

        foreach ($holidays as $holiday) {
            $startsAt = Carbon::create($year, $holiday['month'], $holiday['day'])->startOfDay();

            CalendarEvent::query()->firstOrCreate(
                [
                    'school_id' => $school->id,
                    'title' => $holiday['title'],
                    'starts_at' => $startsAt,
                ],
                [
                    'title_en' => $holiday['title_en'],
                    'event_type' => $holiday['type'],
                    'all_day' => true,
                    'audience_type' => 'whole_school',
                ],
            );
        }
    }
}
