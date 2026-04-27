<?php

namespace App\Http\Controllers\School\Export;

use App\Http\Controllers\Controller;
use App\Services\DueReportService;
use App\Traits\Exportable;
use Illuminate\Http\Request;

class DueReportExportController extends Controller
{
    use Exportable;

    public function __invoke(Request $request, DueReportService $service)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $status = in_array($request->query('status'), ['all', 'defaulter', 'not_defaulter'], true)
            ? $request->query('status')
            : 'all';

        $rows = $service->rowsFor(
            $schoolId,
            $academicYearId,
            $request->filled('class_id')   ? (int) $request->query('class_id')   : null,
            $request->filled('section_id') ? (int) $request->query('section_id') : null,
            $status,
        );

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter($rows, fn($r) =>
                str_contains(mb_strtolower((string) $r['name']), $needle)
                || str_contains((string) $r['father_contact'], $needle)
                || str_contains((string) $r['mother_contact'], $needle)
            ));
        }

        $numericKeys = ['total_fee', 'paid_fee', 'fee_due', 'transport_fee', 'transport_paid', 'transport_due', 'total_balance'];
        $textKeys    = ['name', 'class', 'father_contact', 'mother_contact'];

        $sortKey = $request->query('sort_key');
        $sortDir = $request->query('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        if ($sortKey && (in_array($sortKey, $numericKeys, true) || in_array($sortKey, $textKeys, true))) {
            $isNumeric = in_array($sortKey, $numericKeys, true);
            usort($rows, fn($a, $b) => $isNumeric
                ? ($a[$sortKey] <=> $b[$sortKey])
                : strcasecmp((string) $a[$sortKey], (string) $b[$sortKey]));
            if ($sortDir === 'desc') $rows = array_reverse($rows);
        }

        $headers = [
            'Student Name',
            'Class and Section',
            'Father Contact',
            'Mother Contact',
            'Total Fee',
            'Paid Fee',
            'Fee Due',
            'Transportation Fee',
            'Transportation Fee Paid',
            'Transportation Fee Due',
            'Total Fee Balance',
        ];

        $tableRows = array_map(fn($r) => [
            $r['name'],
            $r['class'],
            $r['father_contact'],
            $r['mother_contact'],
            number_format($r['total_fee'], 2),
            number_format($r['paid_fee'], 2),
            number_format($r['fee_due'], 2),
            number_format($r['transport_fee'], 2),
            number_format($r['transport_paid'], 2),
            number_format($r['transport_due'], 2),
            number_format($r['total_balance'], 2),
        ], $rows);

        $totals = array_fill_keys($numericKeys, 0.0);
        foreach ($rows as $r) {
            foreach ($numericKeys as $k) $totals[$k] += (float) $r[$k];
        }

        $footer = [
            '', '', '', 'Grand Total',
            number_format($totals['total_fee'], 2),
            number_format($totals['paid_fee'], 2),
            number_format($totals['fee_due'], 2),
            number_format($totals['transport_fee'], 2),
            number_format($totals['transport_paid'], 2),
            number_format($totals['transport_due'], 2),
            number_format($totals['total_balance'], 2),
        ];

        return $this->exportResponse(
            $request,
            $headers,
            $tableRows,
            'due-report-' . now()->format('Y-m-d'),
            ['footer' => $footer, 'orientation' => 'landscape']
        );
    }
}
