<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Concerns\RespondsWithCsv;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Services\Fees\FeeReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeReportController extends Controller
{
    use AuthorizesSchoolAdmin;
    use RespondsWithCsv;

    public function __construct(
        private readonly FeeReportService $reports,
    ) {}

    public function collections(Request $request): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'format' => ['nullable', 'in:json,csv'],
        ]);

        $school = $this->schoolForAdmin($request->user(), (int) $data['school_id']);
        $from = Carbon::parse($data['from']);
        $to = Carbon::parse($data['to']);
        abort_if($from->diffInDays($to) > 370, 422, __('edubridge.range_too_long'));

        $report = $this->reports->collections($school, $from, $to);

        if (($data['format'] ?? 'json') === 'csv') {
            return $this->csv(
                "fee-collections-{$report['from']}-{$report['to']}.csv",
                ['Receipt', 'Date', 'Student', 'Admission no.', 'Class', 'Invoice', 'Period', 'Amount (₹)', 'Method', 'Reference', 'Received by'],
                collect($report['payments'])->map(fn ($p) => [
                    $p['receipt_number'], Carbon::parse($p['paid_at'])->toDateString(), $p['student'], $p['admission_number'],
                    $p['class'], $p['invoice_number'], $p['label'], $p['amount_paise'] / 100, $p['method'],
                    $p['reference'], $p['received_by'],
                ]),
            );
        }

        return response()->json($report);
    }

    public function outstanding(Request $request): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'academic_year_id' => ['required', 'integer'],
            'format' => ['nullable', 'in:json,csv'],
        ]);

        $school = $this->schoolForAdmin($request->user(), (int) $data['school_id']);
        $year = AcademicYear::query()->whereKey($data['academic_year_id'])->where('school_id', $school->id)->first();
        abort_unless($year, 422, __('edubridge.invalid_reference'));

        $report = $this->reports->outstanding($school, $year);

        if (($data['format'] ?? 'json') === 'csv') {
            return $this->csv(
                "fee-defaulters-{$year->name}.csv",
                ['Student', 'Admission no.', 'Class', 'Parent', 'Phone', 'Invoice', 'Period', 'Due on', 'Days overdue', 'Balance (₹)'],
                collect($report['defaulters'])->map(fn ($d) => [
                    $d['student'], $d['admission_number'], $d['class'], $d['parent'], $d['phone'],
                    $d['invoice_number'], $d['label'], $d['due_on'], $d['days_overdue'], $d['balance_paise'] / 100,
                ]),
            );
        }

        return response()->json($report);
    }
}
