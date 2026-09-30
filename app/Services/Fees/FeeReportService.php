<?php

namespace App\Services\Fees;

use App\Models\AcademicYear;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\School;
use Illuminate\Support\Carbon;

class FeeReportService
{
    /**
     * Day-book: every live payment received in the range, with totals by method and day.
     */
    public function collections(School $school, Carbon $from, Carbon $to): array
    {
        $payments = FeePayment::query()
            ->where('school_id', $school->id)
            ->whereNull('voided_at')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['invoice:id,number,label,student_id', 'invoice.student:id,name,admission_number,school_class_id', 'invoice.student.schoolClass:id,name', 'receivedBy:id,name'])
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total_paise' => (int) $payments->sum('amount_paise'),
            'count' => $payments->count(),
            'by_method' => $payments->groupBy('method')->map(fn ($g) => (int) $g->sum('amount_paise')),
            'by_day' => $payments->groupBy(fn ($p) => $p->paid_at->toDateString())
                ->map(fn ($g, $day) => ['date' => $day, 'total_paise' => (int) $g->sum('amount_paise'), 'count' => $g->count()])
                ->values(),
            'payments' => $payments->map(fn (FeePayment $p) => [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number,
                'paid_at' => $p->paid_at->toIso8601String(),
                'student' => $p->invoice->student->name,
                'admission_number' => $p->invoice->student->admission_number,
                'class' => $p->invoice->student->schoolClass?->name,
                'invoice_number' => $p->invoice->number,
                'label' => $p->invoice->label,
                'amount_paise' => $p->amount_paise,
                'method' => $p->method,
                'reference' => $p->reference,
                'received_by' => $p->receivedBy?->name,
            ]),
        ];
    }

    /**
     * Billed / collected / due per class for the year, and every overdue invoice.
     */
    public function outstanding(School $school, AcademicYear $year): array
    {
        $invoices = FeeInvoice::query()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $year->id)
            ->where('status', '!=', 'void')
            ->with(['student:id,name,admission_number,school_class_id', 'student.schoolClass:id,name,sort_order', 'student.parents:id,name,phone'])
            ->get();

        $today = today();

        $classes = $invoices
            ->groupBy(fn ($i) => $i->student->school_class_id ?? 0)
            ->map(fn ($group) => [
                'class' => $group->first()->student->schoolClass?->name,
                'sort' => $group->first()->student->schoolClass?->sort_order ?? PHP_INT_MAX,
                'invoices' => $group->count(),
                'net_paise' => $group->sum(fn ($i) => $i->netPaise()),
                'paid_paise' => (int) $group->sum('paid_paise'),
                'balance_paise' => $group->sum(fn ($i) => $i->balancePaise()),
                'overdue_paise' => $group->filter(fn ($i) => $i->due_on->lt($today))->sum(fn ($i) => $i->balancePaise()),
            ])
            ->sortBy('sort')
            ->map(fn ($row) => collect($row)->except('sort')->all())
            ->values();

        $defaulters = $invoices
            ->filter(fn ($i) => $i->balancePaise() > 0 && $i->due_on->lt($today))
            ->sortByDesc(fn ($i) => $i->due_on->diffInDays($today))
            ->map(fn (FeeInvoice $i) => [
                'invoice_id' => $i->id,
                'invoice_number' => $i->number,
                'label' => $i->label,
                'student_id' => $i->student->id,
                'student' => $i->student->name,
                'admission_number' => $i->student->admission_number,
                'class' => $i->student->schoolClass?->name,
                'parent' => $i->student->parents->first()?->name,
                'phone' => $i->student->parents->first()?->phone,
                'due_on' => $i->due_on->toDateString(),
                'days_overdue' => (int) $i->due_on->diffInDays($today),
                'balance_paise' => $i->balancePaise(),
            ])
            ->values();

        return [
            'academic_year' => $year->name,
            'totals' => [
                'net_paise' => (int) $classes->sum('net_paise'),
                'paid_paise' => (int) $classes->sum('paid_paise'),
                'balance_paise' => (int) $classes->sum('balance_paise'),
                'overdue_paise' => (int) $classes->sum('overdue_paise'),
            ],
            'classes' => $classes,
            'defaulters' => $defaulters,
        ];
    }
}
