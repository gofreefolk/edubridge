<?php

namespace App\Services\Fees;

use App\Models\School;

/**
 * Per-school fee reminder timing, kept in schools.settings.fee_reminders and falling
 * back to config('edubridge.fees') for anything a school has not set.
 */
class FeeReminderSettings
{
    public const KEYS = ['enabled', 'reminder_days_before', 'overdue_every_days', 'overdue_stop_after_days'];

    /**
     * @return array{enabled: bool, reminder_days_before: int, overdue_every_days: int, overdue_stop_after_days: int}
     */
    public function for(School $school): array
    {
        $saved = $school->settings['fee_reminders'] ?? [];
        $defaults = config('edubridge.fees');

        return [
            'enabled' => (bool) ($saved['enabled'] ?? true),
            'reminder_days_before' => (int) ($saved['reminder_days_before'] ?? $defaults['reminder_days_before']),
            'overdue_every_days' => (int) ($saved['overdue_every_days'] ?? $defaults['overdue_every_days']),
            'overdue_stop_after_days' => (int) ($saved['overdue_stop_after_days'] ?? $defaults['overdue_stop_after_days']),
        ];
    }

    public function update(School $school, array $values): array
    {
        $settings = $school->settings ?? [];
        $settings['fee_reminders'] = array_intersect_key([...$this->for($school), ...$values], array_flip(self::KEYS));
        $school->update(['settings' => $settings]);

        return $this->for($school);
    }
}
