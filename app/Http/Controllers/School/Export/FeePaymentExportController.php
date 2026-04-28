<?php

namespace App\Http\Controllers\School\Export;

use App\Http\Controllers\Controller;
use App\Models\FeePayment;
use App\Models\HostelFeePayment;
use App\Models\StationaryFeePayment;
use App\Models\TransportFeePayment;
use App\Traits\Exportable;
use Illuminate\Http\Request;

/**
 * Unified Fee Payment Export.
 *
 * Streams:
 *   ?stream=all|tuition|transport|hostel|stationary
 *
 * `all` (default) merges receipts from all four fee tables into a single
 * export, sorted by date desc, with a "Stream" column distinguishing each.
 * Selecting a single stream restricts to that table.
 */
class FeePaymentExportController extends Controller
{
    use Exportable;

    public function __invoke(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $stream = strtolower((string) $request->input('stream', 'all'));
        $allowedStreams = ['all', 'tuition', 'transport', 'hostel', 'stationary'];
        if (! in_array($stream, $allowedStreams, true)) {
            $stream = 'all';
        }

        $rows = [];

        if ($stream === 'all' || $stream === 'tuition') {
            foreach ($this->fetchTuitionRows($request, $schoolId, $academicYearId) as $row) {
                $rows[] = $row;
            }
        }
        if ($stream === 'all' || $stream === 'transport') {
            foreach ($this->fetchTransportRows($request, $schoolId, $academicYearId) as $row) {
                $rows[] = $row;
            }
        }
        if ($stream === 'all' || $stream === 'hostel') {
            foreach ($this->fetchHostelRows($request, $schoolId, $academicYearId) as $row) {
                $rows[] = $row;
            }
        }
        if ($stream === 'all' || $stream === 'stationary') {
            foreach ($this->fetchStationaryRows($request, $schoolId, $academicYearId) as $row) {
                $rows[] = $row;
            }
        }

        // Sort by date desc (column index 2 in the row)
        usort($rows, fn($a, $b) => strcmp((string) $b[2], (string) $a[2]));

        // Re-index serial numbers and compute total
        $totalAmount = 0.0;
        foreach ($rows as $i => &$row) {
            $row[0] = $i + 1;
            // Amount is column index 8 (after we add Stream at index 7)
            $totalAmount += (float) str_replace(',', '', $row[8]);
        }
        unset($row);

        $headers = ['S.No', 'Receipt #', 'Date', 'Student', 'Admission No', 'Fee Group', 'Fee Head', 'Stream', 'Amount', 'Payment Mode', 'Status', 'Collected By'];
        $footer  = ['', '', '', '', '', '', '', 'TOTAL', number_format($totalAmount, 2), '', '', ''];

        $filename = $stream === 'all'
            ? 'fee-payments-all-' . now()->format('Y-m-d')
            : "fee-payments-{$stream}-" . now()->format('Y-m-d');

        return $this->exportResponse($request, $headers, $rows, $filename, [
            'footer'      => $footer,
            'orientation' => 'landscape',
        ]);
    }

    private function fetchTuitionRows(Request $request, int $schoolId, int $academicYearId): array
    {
        $query = FeePayment::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->with(['student', 'feeHead.feeGroup', 'collectedBy:id,name']);

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('from'))   $query->where('paid_at', '>=', $request->from);
        if ($request->filled('to'))     $query->where('paid_at', '<=', $request->to . ' 23:59:59');
        if ($request->filled('class_id')) {
            $query->whereHas('student.currentAcademicHistory', fn($q) => $q->where('class_id', $request->class_id));
        }

        return $query->orderByDesc('paid_at')->get()->map(fn ($p) => [
            0,                                              // S.No (re-indexed later)
            $p->receipt_no ?? '',
            $p->paid_at ? \Carbon\Carbon::parse($p->paid_at)->format('Y-m-d') : '',
            trim(($p->student?->first_name ?? '') . ' ' . ($p->student?->last_name ?? '')),
            $p->student?->admission_no ?? '',
            $p->feeHead?->feeGroup?->name ?? '',
            $p->feeHead?->name ?? '',
            'Tuition',
            number_format((float) $p->amount, 2, '.', ''),
            ucfirst(str_replace('_', ' ', $p->payment_mode ?? '')),
            ucfirst((string) $p->status),
            $p->collectedBy?->name ?? '',
        ])->all();
    }

    private function fetchTransportRows(Request $request, int $schoolId, int $academicYearId): array
    {
        $query = TransportFeePayment::where('school_id', $schoolId)
            ->with(['student', 'allocation.route', 'collectedBy:id,name']);

        if ($request->filled('from')) $query->whereDate('payment_date', '>=', $request->from);
        if ($request->filled('to'))   $query->whereDate('payment_date', '<=', $request->to);
        if ($request->filled('class_id')) {
            $query->whereHas('student.currentAcademicHistory', fn($q) => $q->where('class_id', $request->class_id));
        }

        return $query->orderByDesc('payment_date')->get()->map(fn ($p) => [
            0,
            $p->receipt_no ?? '',
            $p->payment_date ? \Carbon\Carbon::parse($p->payment_date)->format('Y-m-d') : '',
            trim(($p->student?->first_name ?? '') . ' ' . ($p->student?->last_name ?? '')),
            $p->student?->admission_no ?? '',
            'Transport',
            $p->allocation?->route?->route_name ?? 'Route',
            'Transport',
            number_format((float) $p->amount_paid, 2, '.', ''),
            ucfirst(str_replace('_', ' ', $p->payment_mode ?? '')),
            ucfirst((string) ($p->status ?? '')),
            $p->collectedBy?->name ?? '',
        ])->all();
    }

    private function fetchHostelRows(Request $request, int $schoolId, int $academicYearId): array
    {
        $query = HostelFeePayment::where('school_id', $schoolId)
            ->with(['student', 'allocation.bed.room.hostel', 'collectedBy:id,name']);

        if ($request->filled('from')) $query->whereDate('payment_date', '>=', $request->from);
        if ($request->filled('to'))   $query->whereDate('payment_date', '<=', $request->to);
        if ($request->filled('class_id')) {
            $query->whereHas('student.currentAcademicHistory', fn($q) => $q->where('class_id', $request->class_id));
        }

        return $query->orderByDesc('payment_date')->get()->map(fn ($p) => [
            0,
            $p->receipt_no ?? '',
            $p->payment_date ? \Carbon\Carbon::parse($p->payment_date)->format('Y-m-d') : '',
            trim(($p->student?->first_name ?? '') . ' ' . ($p->student?->last_name ?? '')),
            $p->student?->admission_no ?? '',
            'Hostel',
            $p->allocation?->bed?->room?->hostel?->name ?? 'Hostel',
            'Hostel',
            number_format((float) $p->amount_paid, 2, '.', ''),
            ucfirst(str_replace('_', ' ', $p->payment_mode ?? '')),
            ucfirst((string) ($p->status ?? '')),
            $p->collectedBy?->name ?? '',
        ])->all();
    }

    private function fetchStationaryRows(Request $request, int $schoolId, int $academicYearId): array
    {
        $query = StationaryFeePayment::where('school_id', $schoolId)
            ->with(['student', 'allocation', 'collectedBy:id,name']);

        if ($request->filled('from')) $query->whereDate('payment_date', '>=', $request->from);
        if ($request->filled('to'))   $query->whereDate('payment_date', '<=', $request->to);
        if ($request->filled('class_id')) {
            $query->whereHas('student.currentAcademicHistory', fn($q) => $q->where('class_id', $request->class_id));
        }

        return $query->orderByDesc('payment_date')->get()->map(fn ($p) => [
            0,
            $p->receipt_no ?? '',
            $p->payment_date ? \Carbon\Carbon::parse($p->payment_date)->format('Y-m-d') : '',
            trim(($p->student?->first_name ?? '') . ' ' . ($p->student?->last_name ?? '')),
            $p->student?->admission_no ?? '',
            'Stationary',
            'Stationary kit',
            'Stationary',
            number_format((float) $p->amount_paid, 2, '.', ''),
            ucfirst(str_replace('_', ' ', $p->payment_mode ?? '')),
            ucfirst((string) ($p->status ?? '')),
            $p->collectedBy?->name ?? '',
        ])->all();
    }
}
