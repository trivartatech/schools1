<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Master Report — {{ $report['meta']['date_label'] ?? '' }}</title>
    <style>
        @page { margin: 30px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2937; }
        .header { border-bottom: 2px solid #1d4ed8; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { margin: 0; font-size: 18px; color: #1d4ed8; }
        .header .school { font-size: 13px; font-weight: bold; }
        .header .date { font-size: 11px; color: #475569; }
        h2 { font-size: 12px; margin: 16px 0 6px; color: #1d4ed8; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
        h3 { font-size: 11px; margin: 10px 0 4px; color: #334155; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { padding: 4px 6px; border: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-size: 10px; }
        .kpi-grid { width: 100%; }
        .kpi-grid td { width: 16.6%; text-align: center; padding: 8px 4px; }
        .kpi-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .kpi-value { font-size: 14px; font-weight: bold; color: #0f172a; margin-top: 2px; }
        .delta-up { color: #16a34a; }
        .delta-down { color: #dc2626; }
        .alert-box { background: #fef3c7; border-left: 3px solid #f59e0b; padding: 6px 10px; margin: 4px 0; }
        .alert-red { background: #fee2e2; border-left-color: #dc2626; }
        .right { text-align: right; }
        .center { text-align: center; }
        .muted { color: #64748b; font-size: 9.5px; }
        .net-pos { color: #16a34a; }
        .net-neg { color: #dc2626; }
        .pct-bar-wrap { width: 80px; background: #e2e8f0; height: 8px; display: inline-block; border-radius: 2px; overflow: hidden; vertical-align: middle; }
        .pct-bar { height: 100%; background: #1d4ed8; }
        .footer { margin-top: 16px; padding-top: 6px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; text-align: center; }
        /*
         * Don't force whole sections to one page — that left a giant empty
         * gap at the bottom of page 1 whenever a section's table was too tall
         * to fit. Instead, only keep the section heading + first row glued so
         * a heading never appears alone at the bottom of a page.
         */
        h2 { page-break-after: avoid; }
        h3 { page-break-after: avoid; }
        table thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    @php
        $meta = $report['meta'] ?? [];
        $kpi  = $report['kpi']  ?? [];
        $sections = $meta['sections_enabled'] ?? \App\Models\DailyReportSetting::ALL_SECTIONS;
        $isWeekly = ($meta['mode'] ?? 'daily') === 'weekly';
        $rupees = fn($n) => '₹ ' . number_format((float) $n, 0, '.', ',');
        $hasSection = fn($key) => in_array($key, $sections, true);
    @endphp

    <div class="header">
        <h1>{{ $isWeekly ? 'Weekly Digest' : 'Daily Master Report' }}</h1>
        <div class="school">{{ $school->name ?? '' }}</div>
        <div class="date">{{ $meta['date_label'] ?? '' }} &nbsp;·&nbsp; Generated {{ \Carbon\Carbon::parse($meta['generated_at'] ?? now())->format('d M Y, h:i A') }}</div>
    </div>

    {{-- KPI strip --}}
    <table class="kpi-grid">
        <tr>
            @if(isset($kpi['attendance_pct']))
            <td>
                <div class="kpi-label">Attendance</div>
                <div class="kpi-value">{{ round($kpi['attendance_pct']['value'] ?? 0, 1) }}%</div>
                @if(($kpi['attendance_pct']['vs_yesterday_delta'] ?? null) !== null)
                    <div class="muted {{ ($kpi['attendance_pct']['vs_yesterday_delta'] ?? 0) >= 0 ? 'delta-up' : 'delta-down' }}">
                        {{ ($kpi['attendance_pct']['vs_yesterday_delta'] >= 0 ? '+' : '') . $kpi['attendance_pct']['vs_yesterday_delta'] }} pp vs yest
                    </div>
                @endif
            </td>
            @endif
            @if(isset($kpi['fee_total']))
            <td>
                <div class="kpi-label">Fees Collected</div>
                <div class="kpi-value">{{ $rupees($kpi['fee_total']['value'] ?? 0) }}</div>
                @if(($kpi['fee_total']['vs_yesterday_delta'] ?? null) !== null)
                    <div class="muted {{ ($kpi['fee_total']['vs_yesterday_delta'] ?? 0) >= 0 ? 'delta-up' : 'delta-down' }}">
                        {{ ($kpi['fee_total']['vs_yesterday_delta'] >= 0 ? '+' : '') . $kpi['fee_total']['vs_yesterday_delta'] }}% vs yest
                    </div>
                @endif
            </td>
            @endif
            @if(isset($kpi['expense_total']))
            <td>
                <div class="kpi-label">Expenses</div>
                <div class="kpi-value">{{ $rupees($kpi['expense_total']['value'] ?? 0) }}</div>
            </td>
            @endif
            @if(isset($kpi['net_position']))
            <td>
                <div class="kpi-label">Net Cash</div>
                <div class="kpi-value {{ ($kpi['net_position']['value'] ?? 0) >= 0 ? 'net-pos' : 'net-neg' }}">
                    {{ ($kpi['net_position']['value'] ?? 0) >= 0 ? '+' : '−' }}{{ $rupees(abs($kpi['net_position']['value'] ?? 0)) }}
                </div>
            </td>
            @endif
            @if(isset($kpi['new_admissions']))
            <td>
                <div class="kpi-label">New Admissions</div>
                <div class="kpi-value">{{ (int) ($kpi['new_admissions']['value'] ?? 0) }}</div>
            </td>
            @endif
            @if(isset($kpi['visitors']))
            <td>
                <div class="kpi-label">Visitors</div>
                <div class="kpi-value">{{ (int) ($kpi['visitors']['value'] ?? 0) }}</div>
            </td>
            @endif
        </tr>
    </table>

    {{-- Alerts --}}
    @if($hasSection('alerts') && !empty($report['alerts']))
        <div class="section">
            <h2>Alerts &amp; Flags</h2>
            @foreach($report['alerts'] as $alert)
                <div class="alert-box {{ ($alert['severity'] ?? '') === 'red' ? 'alert-red' : '' }}">
                    <strong>{{ $alert['label'] ?? '' }}</strong> — {{ $alert['count'] ?? 0 }}
                    @if(!empty($alert['items']))
                        <div class="muted" style="margin-top:3px;">
                            @foreach(array_slice($alert['items'], 0, 6) as $item)
                                @if($alert['type'] === 'low_attendance_classes')
                                    {{ $item['class'] }}{{ $item['section'] ? ' - ' . $item['section'] : '' }} ({{ $item['pct'] }}%);
                                @elseif($alert['type'] === 'repeat_absentees')
                                    {{ $item['name'] }} ({{ $item['class'] }}{{ $item['section'] ? ' - ' . $item['section'] : '' }});
                                @elseif($alert['type'] === 'oversized_expenses')
                                    {{ $item['title'] }} ({{ $rupees($item['amount']) }});
                                @elseif($alert['type'] === 'stale_visitors')
                                    {{ $item['name'] }} ({{ $item['in_time'] }});
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Highlights --}}
    @if($hasSection('highlights') && !empty($report['highlights']))
        @php $hl = $report['highlights']; @endphp
        @if(!empty($hl['student_of_the_day']) || !empty($hl['top_class']) || !empty($hl['staff_shoutout']))
        <div class="section">
            <h2>Highlights of the Day</h2>
            <table>
                <tr>
                    @if(!empty($hl['student_of_the_day']))
                        <td><strong>★ Student:</strong> {{ $hl['student_of_the_day']['name'] }}
                            <span class="muted">({{ $hl['student_of_the_day']['class'] }}{{ $hl['student_of_the_day']['section'] ? ' - ' . $hl['student_of_the_day']['section'] : '' }})</span>
                            <br><span class="muted">{{ $hl['student_of_the_day']['streak'] }}-day perfect streak</span>
                        </td>
                    @endif
                    @if(!empty($hl['top_class']))
                        <td><strong>★ Top Class:</strong> {{ $hl['top_class']['class'] }}{{ $hl['top_class']['section'] ? ' - ' . $hl['top_class']['section'] : '' }}
                            <br><span class="muted">{{ $hl['top_class']['pct'] }}% — {{ $hl['top_class']['present'] }}/{{ $hl['top_class']['enrolled'] }} present</span>
                        </td>
                    @endif
                    @if(!empty($hl['staff_shoutout']))
                        <td><strong>★ Staff:</strong> {{ $hl['staff_shoutout']['name'] }}
                            @if(!empty($hl['staff_shoutout']['designation']))<span class="muted">({{ $hl['staff_shoutout']['designation'] }})</span>@endif
                            <br><span class="muted">{{ $hl['staff_shoutout']['streak'] }}-day perfect punctuality</span>
                        </td>
                    @endif
                </tr>
            </table>
        </div>
        @endif
    @endif

    {{-- Attendance --}}
    @if($hasSection('attendance') && !empty($report['attendance']))
        <div class="section">
            <h2>Attendance — Class &amp; Section</h2>
            @php $rows = $report['attendance']['class_section_table'] ?? []; @endphp
            @if(!empty($rows))
                <table>
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Section</th>
                            <th class="right">Enrolled</th>
                            <th class="right">Present</th>
                            <th class="right">Absent</th>
                            <th class="right">Unmarked</th>
                            <th class="right">%</th>
                            <th>Bar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td>{{ $row['class'] }}</td>
                                <td>{{ $row['section'] ?? '—' }}</td>
                                <td class="right">{{ $row['enrolled'] }}</td>
                                <td class="right">{{ $row['present'] }}</td>
                                <td class="right">{{ $row['absent'] }}</td>
                                <td class="right">{{ $row['unmarked'] }}</td>
                                <td class="right"><strong>{{ $row['pct'] }}%</strong></td>
                                <td>
                                    <div class="pct-bar-wrap"><div class="pct-bar" style="width: {{ min(100, $row['pct']) }}%;"></div></div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="muted">No attendance recorded for this date.</p>
            @endif

            @if(!empty($report['attendance']['unmarked_classes']))
                <h3>Classes still pending attendance</h3>
                <table>
                    <thead><tr><th>Class</th><th>Section</th><th class="right">Enrolled</th><th>Class Teacher</th></tr></thead>
                    <tbody>
                        @foreach($report['attendance']['unmarked_classes'] as $u)
                            <tr>
                                <td>{{ $u['class'] }}</td>
                                <td>{{ $u['section'] }}</td>
                                <td class="right">{{ $u['enrolled'] }}</td>
                                <td>{{ $u['teacher'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($report['attendance']['staff']))
                @php $sa = $report['attendance']['staff']; @endphp
                <h3>Staff Attendance</h3>
                <table>
                    <thead><tr><th>Total</th><th>Present</th><th>Absent</th><th>Leave</th><th>Unmarked</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>{{ $sa['total'] }}</td>
                            <td>{{ $sa['present'] }}</td>
                            <td>{{ $sa['absent'] }}</td>
                            <td>{{ $sa['leave'] }}</td>
                            <td>{{ $sa['unmarked'] }}</td>
                        </tr>
                    </tbody>
                </table>

                @if(!empty($sa['absent_list']))
                    <h3>Absent / On Leave Today</h3>
                    <table>
                        <thead><tr><th>Name</th><th>Designation</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach($sa['absent_list'] as $st)
                                <tr>
                                    <td>{{ $st['name'] }}</td>
                                    <td>{{ $st['designation'] ?? '—' }}</td>
                                    <td>{{ ucfirst($st['status']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if(!empty($sa['unmarked_list']))
                    <h3>Unmarked Staff</h3>
                    <table>
                        <thead><tr><th>Name</th><th>Designation</th></tr></thead>
                        <tbody>
                            @foreach($sa['unmarked_list'] as $st)
                                <tr>
                                    <td>{{ $st['name'] }}</td>
                                    <td>{{ $st['designation'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endif
        </div>
    @endif

    {{-- Fees --}}
    @if($hasSection('fees') && !empty($report['fees']))
        @php $f = $report['fees']; @endphp
        <div class="section">
            <h2>Fees Collected (Money In)</h2>
            <p><strong>Total: {{ $rupees($f['total'] ?? 0) }}</strong></p>

            @if(!empty($f['streams']))
                <table>
                    <thead><tr><th>Stream</th><th class="right">Receipts</th><th class="right">Amount</th></tr></thead>
                    <tbody>
                        @foreach(['tuition' => 'Tuition', 'transport' => 'Transport', 'hostel' => 'Hostel', 'stationary' => 'Stationary'] as $key => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="right">{{ $f['streams'][$key]['count'] ?? 0 }}</td>
                                <td class="right">{{ $rupees($f['streams'][$key]['amount'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($f['by_payment_mode']))
                <h3>By Payment Mode</h3>
                <table>
                    <thead><tr><th>Mode</th><th class="right">Receipts</th><th class="right">Amount</th></tr></thead>
                    <tbody>
                        @foreach($f['by_payment_mode'] as $m)
                            <tr>
                                <td>{{ ucfirst($m['mode']) }}</td>
                                <td class="right">{{ $m['count'] }}</td>
                                <td class="right">{{ $rupees($m['amount']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($f['by_class']))
                <h3>Top Classes</h3>
                <table>
                    <thead><tr><th>Class</th><th class="right">Receipts</th><th class="right">Amount</th></tr></thead>
                    <tbody>
                        @foreach($f['by_class'] as $c)
                            <tr><td>{{ $c['class'] }}</td><td class="right">{{ $c['count'] }}</td><td class="right">{{ $rupees($c['amount']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($f['top_collectors']))
                <h3>Top Collectors</h3>
                <table>
                    <thead><tr><th>Staff</th><th class="right">Receipts</th><th class="right">Amount</th></tr></thead>
                    <tbody>
                        @foreach($f['top_collectors'] as $tc)
                            <tr><td>{{ $tc['name'] }}</td><td class="right">{{ $tc['count'] }}</td><td class="right">{{ $rupees($tc['amount']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($f['pending_dues']))
                <p class="muted" style="margin-top: 6px;">
                    Outstanding dues: <strong>{{ $rupees($f['pending_dues']['amount']) }}</strong> across {{ $f['pending_dues']['students'] }} students.
                </p>
            @endif
        </div>
    @endif

    {{-- Expenses --}}
    @if($hasSection('expenses') && !empty($report['expenses']))
        @php $e = $report['expenses']; @endphp
        <div class="section">
            <h2>Expenses (Money Out)</h2>
            <p><strong>Total: {{ $rupees($e['total'] ?? 0) }}</strong></p>

            @if(!empty($e['by_category']))
                <h3>By Category</h3>
                <table>
                    <thead><tr><th>Category</th><th class="right">Vouchers</th><th class="right">Amount</th></tr></thead>
                    <tbody>
                        @foreach($e['by_category'] as $c)
                            <tr><td>{{ $c['category'] }}</td><td class="right">{{ $c['count'] }}</td><td class="right">{{ $rupees($c['amount']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($e['top_vouchers']))
                <h3>Top Vouchers</h3>
                <table>
                    <thead><tr><th>Title</th><th>Category</th><th>Mode</th><th class="right">Amount</th><th>Recorded By</th></tr></thead>
                    <tbody>
                        @foreach($e['top_vouchers'] as $v)
                            <tr>
                                <td>{{ $v['title'] }}</td>
                                <td>{{ $v['category'] }}</td>
                                <td>{{ ucfirst($v['mode']) }}</td>
                                <td class="right">{{ $rupees($v['amount']) }}</td>
                                <td>{{ $v['recorded_by'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- Cash flow --}}
    @if($hasSection('cash') && !empty($report['cash']))
        @php $c = $report['cash']; @endphp
        <div class="section">
            <h2>Cash Flow Today</h2>
            <table>
                <thead><tr><th class="center">Cash In</th><th class="center">Cash Out</th><th class="center">Net Drawer Movement</th></tr></thead>
                <tbody>
                    <tr>
                        <td class="center">{{ $rupees($c['cash_in']) }}</td>
                        <td class="center">{{ $rupees($c['cash_out']) }}</td>
                        <td class="center {{ $c['net'] >= 0 ? 'net-pos' : 'net-neg' }}">
                            <strong>{{ ($c['net'] >= 0 ? '+' : '−') }}{{ $rupees(abs($c['net'])) }}</strong>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    {{-- Admissions --}}
    @if($hasSection('admissions') && !empty($report['admissions']))
        @php $adm = $report['admissions']; @endphp
        <div class="section">
            <h2>New Admissions Today</h2>
            <p><strong>{{ $adm['count'] }}</strong> admission{{ $adm['count'] === 1 ? '' : 's' }}.</p>
            @if(!empty($adm['students']))
                <table>
                    <thead><tr><th>Name</th><th>Admission #</th><th>Class</th><th>Section</th></tr></thead>
                    <tbody>
                        @foreach($adm['students'] as $s)
                            <tr><td>{{ $s['name'] }}</td><td>{{ $s['admission_no'] }}</td><td>{{ $s['class'] }}</td><td>{{ $s['section'] ?? '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- Day events --}}
    @if($hasSection('events') && !empty($report['events']))
        @php $ev = $report['events']; @endphp
        <div class="section">
            <h2>Day Events</h2>
            @if(!empty($ev['visitors']))
                <p><strong>Visitors:</strong> {{ $ev['visitors']['total'] }} total · {{ $ev['visitors']['signed_out'] }} signed out · {{ $ev['visitors']['still_in'] }} still inside</p>
            @endif
            @if(!empty($ev['birthdays']))
                <p><strong>Birthdays today:</strong>
                    @foreach($ev['birthdays'] as $b)<span>{{ $b['name'] }}@if(!$loop->last), @endif</span>@endforeach
                </p>
            @endif
            @if(!empty($ev['holidays']))
                <p><strong>Holidays:</strong>
                    @foreach($ev['holidays'] as $h){{ $h['title'] }}@if(!$loop->last), @endif @endforeach
                </p>
            @endif
        </div>
    @endif

    {{-- Tomorrow's outlook --}}
    @if($hasSection('outlook') && !empty($report['outlook']))
        @php $o = $report['outlook']; @endphp
        <div class="section">
            <h2>Tomorrow's Outlook ({{ $o['date_label'] ?? '' }})</h2>
            @if(!empty($o['holidays']))
                <p><strong>Holidays:</strong> {{ implode(', ', $o['holidays']) }}</p>
            @endif
            @if(($o['birthdays'] ?? 0) > 0)
                <p><strong>Birthdays tomorrow:</strong> {{ $o['birthdays'] }}</p>
            @endif
            @if(empty($o['holidays']) && ($o['birthdays'] ?? 0) === 0)
                <p class="muted">Nothing special scheduled.</p>
            @endif
        </div>
    @endif

    <div class="footer">
        Generated by {{ $school->name ?? 'School ERP' }} · {{ \Carbon\Carbon::parse($meta['generated_at'] ?? now())->format('d M Y, h:i A') }}
    </div>
</body>
</html>
