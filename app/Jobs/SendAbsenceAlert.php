<?php

namespace App\Jobs;

use App\Models\AttendanceRecord;
use App\Models\WhatsAppOptIn;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Tells a student's opted-in parents on WhatsApp that the child was marked absent.
 * Sent at most once per attendance record.
 */
class SendAbsenceAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $attendanceRecordId,
    ) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $record = AttendanceRecord::query()
            ->with(['student.school', 'student.parents'])
            ->find($this->attendanceRecordId);

        $student = $record?->student;
        $school = $student?->school;

        if (! $record || $record->status !== 'absent' || ! $school?->whatsapp_bridge_enabled) {
            return;
        }

        // Claim the record so a retry or a double submit never alerts twice.
        $claimed = AttendanceRecord::query()
            ->whereKey($record->id)
            ->whereNull('absence_alert_sent_at')
            ->where('status', 'absent')
            ->update(['absence_alert_sent_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $optedIn = WhatsAppOptIn::query()
            ->where('school_id', $school->id)
            ->where('opted_in', true)
            ->whereIn('user_id', $student->parents->pluck('id'))
            ->pluck('user_id');

        $message = __('edubridge.absence_alert', [
            'school' => $school->name,
            'student' => $student->name,
            'date' => $record->date->format('d M Y'),
        ]);

        foreach ($student->parents->whereIn('id', $optedIn) as $parent) {
            $whatsApp->sendToUser($parent, $message, 'absence_alert', $school->id, checkOptIn: false);
        }
    }
}
