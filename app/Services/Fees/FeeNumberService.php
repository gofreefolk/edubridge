<?php

namespace App\Services\Fees;

use App\Models\AcademicYear;
use App\Models\FeeInvoice;
use App\Models\FeeNumberSequence;
use App\Models\FeePayment;
use App\Models\School;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * School-configurable invoice / receipt numbers.
 *
 * Format tokens: {SEQ} or {SEQ:n} (counter, zero-padded to n digits; required, once),
 * {YYYY}, {YY}, {MM} (document date), {AY} (academic year name, e.g. 2025-26),
 * {CODE} (school code). Everything else is literal: letters, digits, space and / - _ . #
 */
class FeeNumberService
{
    private const TOKEN = '/\{(SEQ(?::(\d{1,2}))?|YYYY|YY|MM|AY|CODE)\}/';

    public function validateFormat(string $format): void
    {
        if (preg_match_all('/\{SEQ(?::\d{1,2})?\}/', $format) !== 1) {
            throw new InvalidArgumentException('fee_number_needs_seq');
        }

        $literal = preg_replace(self::TOKEN, '', $format);

        if (str_contains($literal, '{') || str_contains($literal, '}') || ! preg_match('/^[A-Za-z0-9 \/\-_.#]*$/', $literal)) {
            throw new InvalidArgumentException('fee_number_bad_format');
        }

        if (preg_match('/\{SEQ:(\d+)\}/', $format, $m) && ((int) $m[1] < 1 || (int) $m[1] > 10)) {
            throw new InvalidArgumentException('fee_number_bad_format');
        }
    }

    public function render(string $format, int $sequence, Carbon $date, School $school, ?AcademicYear $year): string
    {
        return preg_replace_callback(self::TOKEN, fn (array $m) => match (true) {
            str_starts_with($m[1], 'SEQ') => isset($m[2]) && $m[2] !== ''
                ? str_pad((string) $sequence, (int) $m[2], '0', STR_PAD_LEFT)
                : (string) $sequence,
            $m[1] === 'YYYY' => $date->format('Y'),
            $m[1] === 'YY' => $date->format('y'),
            $m[1] === 'MM' => $date->format('m'),
            $m[1] === 'AY' => $year?->name ?? $date->format('Y'),
            $m[1] === 'CODE' => $school->code,
        }, $format);
    }

    public function sequence(School $school, string $type): FeeNumberSequence
    {
        return FeeNumberSequence::query()->firstOrCreate(
            ['school_id' => $school->id, 'type' => $type],
            ['format' => FeeNumberSequence::DEFAULT_FORMATS[$type], 'reset' => 'academic_year', 'next_number' => 1],
        );
    }

    /**
     * Hands out the next number. Must run inside the caller's transaction so a failed
     * invoice / payment insert does not burn a number.
     */
    public function allocate(School $school, string $type, Carbon $date, ?AcademicYear $year): string
    {
        $this->sequence($school, $type);

        return DB::transaction(function () use ($school, $type, $date, $year) {
            $sequence = FeeNumberSequence::query()
                ->where('school_id', $school->id)
                ->where('type', $type)
                ->lockForUpdate()
                ->firstOrFail();

            $period = $this->period($sequence->reset, $date, $year);

            if ($sequence->reset !== 'never' && $sequence->period !== $period) {
                $sequence->period = $period;
                $sequence->next_number = 1;
            }

            // Skip numbers already taken, e.g. after the admin changed the format or reset.
            $next = $sequence->next_number;
            for ($attempt = 0; $attempt < 1000; $attempt++, $next++) {
                $number = $this->render($sequence->format, $next, $date, $school, $year);

                if (! $this->taken($school, $type, $number)) {
                    $sequence->next_number = $next + 1;
                    $sequence->save();

                    return $number;
                }
            }

            throw new InvalidArgumentException('fee_number_exhausted');
        });
    }

    /**
     * @return array{format: string, reset: string, next_number: int, preview: string}
     */
    public function settings(School $school, string $type): array
    {
        $sequence = $this->sequence($school, $type);
        $year = $this->currentYear($school);
        $date = today();

        $next = $sequence->reset !== 'never' && $sequence->period !== $this->period($sequence->reset, $date, $year)
            ? 1
            : $sequence->next_number;

        return [
            'format' => $sequence->format,
            'reset' => $sequence->reset,
            'next_number' => $next,
            'preview' => $this->render($sequence->format, $next, $date, $school, $year),
        ];
    }

    public function update(School $school, string $type, string $format, string $reset, ?int $nextNumber): array
    {
        $this->validateFormat($format);

        if (! in_array($reset, FeeNumberSequence::RESETS, true)) {
            throw new InvalidArgumentException('fee_number_bad_format');
        }

        $sequence = $this->sequence($school, $type);
        $current = $this->settings($school, $type)['next_number'];

        $sequence->fill([
            'format' => $format,
            'reset' => $reset,
            // Pin the counter to the current period so the change applies right away.
            'period' => $this->period($reset, today(), $this->currentYear($school)),
            'next_number' => $nextNumber ?? $current,
        ])->save();

        return $this->settings($school, $type);
    }

    private function period(string $reset, Carbon $date, ?AcademicYear $year): ?string
    {
        return match ($reset) {
            'never' => null,
            'yearly' => $date->format('Y'),
            default => $year ? 'ay:'.$year->id : $date->format('Y'),
        };
    }

    private function taken(School $school, string $type, string $number): bool
    {
        $model = $type === 'invoice' ? FeeInvoice::class : FeePayment::class;
        $column = $type === 'invoice' ? 'number' : 'receipt_number';

        return $model::query()->where('school_id', $school->id)->where($column, $number)->exists();
    }

    private function currentYear(School $school): ?AcademicYear
    {
        return AcademicYear::query()
            ->where('school_id', $school->id)
            ->orderByDesc('is_current')
            ->orderByDesc('starts_on')
            ->first();
    }
}
