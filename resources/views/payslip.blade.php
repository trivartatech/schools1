<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Payslip – {{ $payroll->staff->user->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Helvetica, sans-serif; font-size: 12px; color: #1a1a1a; background: #fff; }

        .header { background: #1e40af; color: #fff; padding: 22px 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 20px; font-weight: 700; letter-spacing: 1px; }
        .header .sub { font-size: 11px; opacity: 0.85; margin-top: 3px; }
        .header .payslip-title { text-align: right; }
        .header .payslip-title h2 { font-size: 16px; font-weight: 700; }

        .body { padding: 24px 30px; }

        /* Employee Info Band */
        .emp-info { display: flex; justify-content: space-between; border: 1px solid #e5e7eb; border-radius: 6px; padding: 14px 18px; background: #f8fafc; margin-bottom: 20px; }
        .emp-block { flex: 1; }
        .emp-block + .emp-block { border-left: 1px solid #e5e7eb; padding-left: 20px; margin-left: 20px; }
        .emp-label { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .emp-value { font-size: 12px; font-weight: 600; color: #111827; margin-top: 2px; }

        /* Earnings & Deductions Table */
        .tables { display: flex; gap: 16px; margin-bottom: 20px; }
        .table-section { flex: 1; }
        .table-section h4 { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #fff; background: #1e40af; padding: 6px 12px; border-radius: 4px 4px 0 0; }
        .table-section table { width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb; border-top: none; }
        .table-section table tr td { padding: 7px 12px; border-bottom: 1px solid #f3f4f6; }
        .table-section table tr:last-child td { border-bottom: none; }
        .table-section table tr td:last-child { text-align: right; font-weight: 600; }
        .table-section table tr.total-row td { font-weight: 700; background: #eff6ff; border-top: 2px solid #bfdbfe; }

        /* Net Pay Band */
        .net-band { background: linear-gradient(135deg, #1e40af, #3b82f6); color: #fff; border-radius: 6px; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .net-band .label { font-size: 11px; opacity: 0.85; }
        .net-band .amount { font-size: 22px; font-weight: 700; }
        .net-band .meta { text-align: right; font-size: 11px; opacity: 0.85; }

        /* Footer */
        .footer { border-top: 1px solid #e5e7eb; padding-top: 14px; }
        .footer .note { font-size: 10px; color: #9ca3af; margin-bottom: 8px; }
        .sig-row { display: flex; justify-content: space-between; margin-top: 30px; }
        .sig-box { text-align: center; font-size: 10px; color: #6b7280; }
        .sig-box .sig-line { border-top: 1px solid #d1d5db; width: 140px; margin: 0 auto 5px; padding-top: 4px; }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <div>
            <h1>{{ $school->name }}</h1>
            <div class="sub">{{ $school->email ?? '' }} &nbsp;|&nbsp; {{ $school->phone ?? '' }}</div>
        </div>
        <div class="payslip-title">
            <h2>PAYSLIP</h2>
            <div class="sub">{{ $monthName }} {{ $payroll->year }}</div>
        </div>
    </div>

    <div class="body">

        <!-- Employee Info -->
        <div class="emp-info">
            <div class="emp-block">
                <div class="emp-label">Employee Name</div>
                <div class="emp-value">{{ $payroll->staff->user->name }}</div>
            </div>
            <div class="emp-block">
                <div class="emp-label">Employee ID</div>
                <div class="emp-value">{{ $payroll->staff->employee_id ?? '—' }}</div>
            </div>
            <div class="emp-block">
                <div class="emp-label">Designation</div>
                <div class="emp-value">{{ $payroll->staff->designation->name ?? '—' }}</div>
            </div>
            <div class="emp-block">
                <div class="emp-label">Department</div>
                <div class="emp-value">{{ $payroll->staff->department->name ?? '—' }}</div>
            </div>
            <div class="emp-block">
                <div class="emp-label">PAN Number</div>
                <div class="emp-value">{{ $payroll->staff->pan_no ?? '—' }}</div>
            </div>
            <div class="emp-block">
                <div class="emp-label">Bank</div>
                <div class="emp-value">{{ $payroll->staff->bank_name ?? '—' }}</div>
            </div>
        </div>

        <!-- Earnings & Deductions -->
        <div class="tables">
            <div class="table-section">
                <h4>Earnings</h4>
                <table>
                    <tr><td>Basic Pay</td><td>₹{{ number_format($payroll->basic_pay, 2) }}</td></tr>
                    <tr><td>Dearness Allowance (DA)</td><td>₹{{ number_format($da, 2) }}</td></tr>
                    <tr><td>House Rent Allowance (HRA)</td><td>₹{{ number_format($hra, 2) }}</td></tr>
                    <tr><td>Transport Allowance (TA)</td><td>₹{{ number_format($ta, 2) }}</td></tr>
                    <tr class="total-row"><td>Gross Earnings</td><td>₹{{ number_format($gross, 2) }}</td></tr>
                </table>
            </div>
            <div class="table-section">
                <h4>Deductions</h4>
                <table>
                    <tr><td>Provident Fund (PF 12%)</td><td>₹{{ number_format($pf, 2) }}</td></tr>
                    <tr><td>ESI (0.75%)</td><td>₹{{ number_format($esi, 2) }}</td></tr>
                    <tr><td>TDS (Income Tax)</td><td>₹{{ number_format($tds, 2) }}</td></tr>
                    @if($unpaidDays > 0)
                    <tr><td>Unpaid Leave (LWP {{ $unpaidDays }} days)</td><td style="color: #dc2626;">₹{{ number_format($unpaidDed, 2) }}</td></tr>
                    @endif
                    <tr class="total-row"><td>Total Deductions</td><td>₹{{ number_format($payroll->deductions + $unpaidDed, 2) }}</td></tr>
                </table>
            </div>
        </div>

        <!-- Net Pay -->
        <div class="net-band">
            <div>
                <div class="label">Net Pay for {{ $monthName }} {{ $payroll->year }}</div>
                <div class="amount">₹{{ number_format($payroll->net_salary, 2) }}</div>
            </div>
            <div class="meta">
                @if($payroll->status === 'paid')
                    <div>PAID</div>
                    <div>{{ $payroll->payment_date ? \App\Support\Format::date($payroll->payment_date) : '' }}</div>
                    <div style="text-transform: capitalize;">via {{ str_replace('_', ' ', $payroll->payment_mode ?? '') }}</div>
                @else
                    <div>STATUS: GENERATED</div>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="note">* This is a computer-generated payslip and does not require a signature. | Generated on @fdate(now())</div>
            <div class="sig-row">
                <div class="sig-box"><div class="sig-line"></div>Employee Signature</div>
                <div class="sig-box"><div class="sig-line"></div>Accounts Department</div>
                <div class="sig-box"><div class="sig-line"></div>Principal / Authorised Signatory</div>
            </div>
        </div>

    </div>
</body>
</html>
