<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class PayrollController extends Controller
{
    /**
     * Helper: Calculate total unpaid leave days in a specific month
     */
    private function getUnpaidLeaveDays($schoolId, $staffId, $month, $year)
    {
        $startStr = sprintf('%04d-%02d-01', $year, $month);
        $startDate = \Carbon\Carbon::parse($startStr)->startOfDay();
        $endStr    = $startDate->copy()->endOfMonth()->toDateString();
        $endDate   = $startDate->copy()->endOfMonth()->endOfDay();

        $staff = Staff::find($staffId);
        if (!$staff) return 0;

        // 1) Count unpaid leave days from approved leaves
        $leaves = \App\Models\Leave::where('school_id', $schoolId)
            ->where('user_id', $staff->user_id)
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereHas('leaveType', function ($q2) {
                    $q2->where('is_paid', false);
                })
                ->orWhere(function ($q3) {
                    $q3->whereNull('leave_type_id')
                       ->where('leave_type', 'unpaid');
                });
            })
            ->where(function ($q) use ($startStr, $endStr) {
                $q->where('start_date', '<=', $endStr)
                  ->where('end_date', '>=', $startStr);
            })
            ->get();

        $leaveDates = []; // track dates covered by unpaid leaves to avoid double-counting
        $unpaidDays = 0;

        foreach ($leaves as $leave) {
            $ls = \Carbon\Carbon::parse($leave->start_date)->startOfDay();
            $le = \Carbon\Carbon::parse($leave->end_date)->endOfDay();

            $overlapStart = $ls->max($startDate);
            $overlapEnd   = $le->min($endDate);

            if ($overlapStart->lte($overlapEnd)) {
                $cursor = $overlapStart->copy();
                while ($cursor->lte($overlapEnd)) {
                    $leaveDates[$cursor->toDateString()] = true;
                    $unpaidDays++;
                    $cursor->addDay();
                }
            }
        }

        // 2) Count absent days from staff attendance (not already covered by unpaid leaves)
        $absentDates = \App\Models\StaffAttendance::where('school_id', $schoolId)
            ->where('staff_id', $staffId)
            ->where('status', 'absent')
            ->where('date', '>=', $startStr)
            ->where('date', '<=', $endStr)
            ->pluck('date');

        foreach ($absentDates as $date) {
            $dateStr = \Carbon\Carbon::parse($date)->toDateString();
            if (!isset($leaveDates[$dateStr])) {
                $unpaidDays++;
            }
        }

        return $unpaidDays;
    }
    /**
     * Monthly payroll overview
     */
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $month = $request->integer('month', now()->month);
        $year  = $request->integer('year',  now()->year);

        // Get all active staff with their payroll for the selected month
        $staffList = Staff::where('school_id', $schoolId)
            ->where('status', '!=', 'inactive')
            ->with(['user', 'designation', 'department'])
            ->get();

        $payrolls = Payroll::where('school_id', $schoolId)
            ->where('month', $month)
            ->where('year', $year)
            ->with(['staff.user', 'glTransaction:id,transaction_no'])
            ->get()
            ->keyBy('staff_id');

        $summary = [
            'total_staff'   => $staffList->count(),
            'generated'     => $payrolls->count(),
            'paid'          => $payrolls->where('status', 'paid')->count(),
            'total_payout'  => $payrolls->sum('net_salary'),
        ];

        return Inertia::render('School/Staff/Payroll/Index', [
            'staffList' => $staffList,
            'payrollMap'=> $payrolls->toArray(),
            'month'     => $month,
            'year'      => $year,
            'summary'   => $summary,
        ]);
    }

    /**
     * Generate payroll for all staff for a given month
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020|max:2050',
        ]);

        $schoolId = app('current_school_id');
        $month = $validated['month'];
        $year  = $validated['year'];

        $staff = Staff::where('school_id', $schoolId)
            ->where('status', 'active')
            ->get();

        $generated = DB::transaction(function () use ($staff, $schoolId, $month, $year) {
            $generated = 0;
            foreach ($staff as $s) {
                // Skip if payroll already exists
                $exists = Payroll::where('school_id', $schoolId)
                    ->where('staff_id', $s->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->exists();

                if ($exists) continue;

                $basic = (float) $s->basic_salary;

                // Allowances
                $alwCfg = $s->allowances_config ?? [];
                $daPct  = $alwCfg['da_percent'] ?? 52;
                $hraPct = $alwCfg['hra_percent'] ?? 24;
                $taAmt  = $alwCfg['ta_fixed'] ?? 1600.00;

                $da    = round($basic * ($daPct / 100), 2);
                $hra   = round($basic * ($hraPct / 100), 2);
                $ta    = (float) $taAmt;
                $gross = $basic + $da + $hra + $ta;

                // Deductions
                $dedCfg = $s->deductions_config ?? [];
                $pfPct  = $dedCfg['pf_percent'] ?? 12;
                $esiPct = $dedCfg['esi_percent'] ?? 0.75;
                $esiThreshold = $dedCfg['esi_threshold'] ?? 21000;

                $pf  = round($basic * ($pfPct / 100), 2);
                $esi = $gross <= $esiThreshold ? round($gross * ($esiPct / 100), 2) : 0;

                // Tax
                $taxCfg = $s->tax_config ?? [];
                $tdsAmt = (float) ($taxCfg['tds_fixed'] ?? 0);

                $net = round($gross - $pf - $esi - $tdsAmt, 2);

                // Deduct Unpaid Leaves (LWP)
                $unpaidDays   = $this->getUnpaidLeaveDays($schoolId, $s->id, $month, $year);
                $daysInMonth  = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $perDayGross  = $gross / $daysInMonth;
                $lwpDeduction = round($unpaidDays * $perDayGross, 2);

                $net = round($net - $lwpDeduction, 2);

                Payroll::create([
                    'school_id'              => $schoolId,
                    'staff_id'               => $s->id,
                    'month'                  => $month,
                    'year'                   => $year,
                    'basic_pay'              => $basic,
                    'allowances'             => $da + $hra + $ta,
                    'deductions'             => $pf + $esi + $tdsAmt,
                    'unpaid_leave_days'      => $unpaidDays,
                    'unpaid_leave_deduction' => $lwpDeduction,
                    'net_salary'             => $net,
                    'status'                 => 'generated',
                ]);
                $generated++;
            }
            return $generated;
        });

        return back()->with('success', "Payroll generated for {$generated} staff member(s) for {$month}/{$year}.");
    }

    /**
     * Mark one payroll as paid
     */
    public function markPaid(Request $request, Payroll $payroll)
    {
        if ($payroll->school_id !== app('current_school_id')) abort(403);

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_mode' => [
                'required', 'string',
                \Illuminate\Validation\Rule::exists('payment_methods', 'code')
                    ->where('school_id', $payroll->school_id)
                    ->where('is_active', true),
            ],
        ]);

        $payroll->update([
            'status'       => 'paid',
            'payment_date' => $validated['payment_date'],
            'payment_mode' => $validated['payment_mode'],
        ]);

        return back()->with('success', 'Payroll marked as paid.');
    }

    /**
     * Download payslip as PDF
     */
    public function payslip(Payroll $payroll)
    {
        if ($payroll->school_id !== app('current_school_id')) abort(403);

        $payroll->load(['staff.user', 'staff.designation', 'staff.department']);
        $school = app('current_school');

        $months = ['', 'January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];

        $basic  = (float) $payroll->basic_pay;
        $allowances = (float) $payroll->allowances;
        $deductions = (float) $payroll->deductions;
        $gross  = $basic + $allowances;
        $net    = (float) $payroll->net_salary;

        // Use per-staff config for breakdown (same logic as generate)
        $staff = $payroll->staff;
        $alwCfg = $staff->allowances_config ?? [];
        $dedCfg = $staff->deductions_config ?? [];
        $taxCfg = $staff->tax_config ?? [];

        $da  = round($basic * (($alwCfg['da_percent'] ?? 52) / 100), 2);
        $hra = round($basic * (($alwCfg['hra_percent'] ?? 24) / 100), 2);
        $ta  = round($allowances - $da - $hra, 2);
        $pf  = round($basic * (($dedCfg['pf_percent'] ?? 12) / 100), 2);
        $esiPct = ($dedCfg['esi_percent'] ?? 0.75) / 100;
        $esiThreshold = $dedCfg['esi_threshold'] ?? 21000;
        $esi = ($gross <= $esiThreshold) ? round($gross * $esiPct, 2) : 0;
        $tds = (float) ($taxCfg['tds_fixed'] ?? max(0, $deductions - $pf - $esi));

        $pdf = Pdf::loadView('payslip', [
            'payroll'    => $payroll,
            'school'     => $school,
            'monthName'  => $months[$payroll->month] ?? $payroll->month,
            'gross'      => $gross,
            'da'         => $da,
            'hra'        => $hra,
            'ta'         => $ta,
            'pf'         => $pf,
            'esi'        => $esi,
            'tds'        => $tds > 0 ? $tds : 0,
            'unpaidDays' => $payroll->unpaid_leave_days ?? 0,
            'unpaidDed'  => $payroll->unpaid_leave_deduction ?? 0,
        ])->setPaper('A4', 'portrait');

        $filename = 'Payslip_' . str_replace(' ', '_', $payroll->staff?->user?->name ?? 'Staff') . '_' . $months[$payroll->month] . '_' . $payroll->year . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export monthly payroll as Excel
     */
    public function export(Request $request)
    {
        $schoolId = app('current_school_id');
        $month    = $request->integer('month', now()->month);
        $year     = $request->integer('year',  now()->year);

        $months   = ['', 'January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];
        $filename = 'Payroll_' . ($months[$month] ?? $month) . '_' . $year . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\PayrollExport($schoolId, $month, $year),
            $filename
        );
    }

    /**
     * POST /school/payroll/{payroll}/post-gl
     * Manually post a payroll record to the General Ledger
     */
    public function postGl(Payroll $payroll)
    {
        abort_unless($payroll->school_id === app('current_school_id'), 403);
        abort_unless($payroll->status === 'paid', 422, 'Payroll must be in paid status before posting to GL.');

        $tx = app(\App\Services\GlPostingService::class)->postPayroll($payroll);

        if ($tx) {
            return back()->with('success', 'Posted to GL: ' . $tx->transaction_no);
        }

        return back()->with('info', $payroll->gl_transaction_id
            ? 'Already posted to GL.'
            : 'GL not configured for this school.');
    }
}
