<?php

namespace App\Http\Controllers\School\Export;

use App\Http\Controllers\Controller;
use App\Models\FeePayment;
use App\Traits\Exportable;
use Illuminate\Http\Request;

class FeePaymentExportController extends Controller
{
    use Exportable;

    public function __invoke(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $query = FeePayment::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->with(['student', 'feeHead.feeGroup', 'collectedBy:id,name']);

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('from'))     $query->where('paid_at', '>=', $request->from);
        if ($request->filled('to'))       $query->where('paid_at', '<=', $request->to . ' 23:59:59');
        if ($request->filled('class_id')) {
            $query->whereHas('student.currentAcademicHistory', fn($q) => $q->where('class_id', $request->class_id));
        }

        $payments = $query->orderByDesc('paid_at')->get();

        $headers = ['S.No', 'Receipt #', 'Date', 'Student', 'Admission No', 'Fee Group', 'Fee Head', 'Amount', 'Payment Mode', 'Status', 'Collected By'];
        $totalAmount = 0;

        $rows = [];
        foreach ($payments as $i => $p) {
            $totalAmount += (float) $p->amount;
            $rows[] = [
                $i + 1,
                $p->receipt_no ?? '',
                $p->paid_at ? \Carbon\Carbon::parse($p->paid_at)->format('Y-m-d') : '',
                ($p->student?->first_name ?? '') . ' ' . ($p->student?->last_name ?? ''),
                $p->student?->admission_no ?? '',
                $p->feeHead?->feeGroup?->name ?? '',
                $p->feeHead?->name ?? '',
                number_format((float) $p->amount, 2),
                ucfirst(str_replace('_', ' ', $p->payment_mode ?? '')),
                ucfirst($p->status),
                $p->collectedBy?->name ?? '',
            ];
        }

        $footer = ['', '', '', '', '', '', 'TOTAL', number_format($totalAmount, 2), '', '', ''];

        return $this->exportResponse($request, $headers, $rows, 'fee-payments-export-' . now()->format('Y-m-d'), [
            'footer'      => $footer,
            'orientation' => 'landscape',
        ]);
    }
}
