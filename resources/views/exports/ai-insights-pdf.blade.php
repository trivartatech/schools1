<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI Insights — {{ $school->name ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1   { font-size: 18px; margin: 0 0 4px; color: #4f46e5; }
        h2   { font-size: 13px; margin: 16px 0 6px; color: #334155; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
        .meta{ color: #64748b; font-size: 10px; margin-bottom: 14px; }
        .kpi-grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .kpi-grid td { padding: 6px 8px; border: 1px solid #e2e8f0; vertical-align: top; }
        .kpi-grid .label { color: #64748b; font-size: 10px; }
        .kpi-grid .value { font-weight: bold; font-size: 13px; color: #0f172a; }
        .insight-card { border: 1px solid #e2e8f0; padding: 8px 10px; margin-bottom: 6px; }
        .insight-card.success { background: #f0fdf4; border-color: #bbf7d0; }
        .insight-card.warning { background: #fffbeb; border-color: #fde68a; }
        .insight-card.danger  { background: #fff1f2; border-color: #fecdd3; }
        .insight-top { font-size: 10px; color: #64748b; }
        .insight-title { font-weight: bold; font-size: 12px; margin: 2px 0; }
        .insight-action { color: #4f46e5; font-size: 10px; margin-top: 3px; }
        .chart-wrap { margin: 8px 0 14px; }
        .chart-wrap img { max-width: 100%; }
        .chart-caption { font-size: 10px; color: #64748b; margin-top: 2px; }
        table.dues { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.dues th, table.dues td { border: 1px solid #e2e8f0; padding: 4px 6px; text-align: left; }
        table.dues th { background: #f8fafc; }
    </style>
</head>
<body>
    <h1>AI Intelligence Hub Report</h1>
    <div class="meta">
        {{ $school->name ?? '' }} ·
        @fdate($from) → @fdate($to) ·
        Generated @fdatetime($generatedAt)
    </div>

    <h2>Snapshot</h2>
    <table class="kpi-grid">
        <tr>
            <td>
                <div class="label">Active Students</div>
                <div class="value">{{ $snapshot['students']['total'] ?? 0 }}</div>
            </td>
            <td>
                <div class="label">Attendance Today</div>
                <div class="value">{{ ($snapshot['attendance']['percentage'] ?? null) !== null ? $snapshot['attendance']['percentage'] . '%' : '—' }}</div>
            </td>
            <td>
                <div class="label">Staff Present</div>
                <div class="value">{{ ($snapshot['staff']['attendance_pct'] ?? null) !== null ? $snapshot['staff']['attendance_pct'] . '%' : '—' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Collected (Range)</div>
                <div class="value">₹{{ number_format($snapshot['fees']['collected_in_range'] ?? 0, 0) }}</div>
            </td>
            <td>
                <div class="label">Pending Fees</div>
                <div class="value">₹{{ number_format($snapshot['fees']['total_pending'] ?? 0, 0) }}</div>
            </td>
            <td>
                <div class="label">Low Attendance Students</div>
                <div class="value">{{ count($snapshot['attendance']['low_attendance_students'] ?? []) }}</div>
            </td>
        </tr>
    </table>

    @if (!empty($insights))
        <h2>AI-Generated Insights</h2>
        @foreach ($insights as $ins)
            <div class="insight-card {{ $ins['severity'] ?? 'warning' }}">
                <div class="insight-top">
                    {{ strtoupper($ins['category'] ?? '') }} · {{ strtoupper($ins['severity'] ?? '') }} ·
                    {{ $ins['metric'] ?? '' }}
                </div>
                <div class="insight-title">{{ $ins['title'] ?? '' }}</div>
                <div>{{ $ins['insight'] ?? '' }}</div>
                @if (!empty($ins['action']))
                    <div class="insight-action">→ {{ $ins['action'] }}</div>
                @endif
            </div>
        @endforeach
    @endif

    @if (!empty($chartImages))
        <h2>Charts</h2>
        @foreach ($chartImages as $name => $b64)
            <div class="chart-wrap">
                <img src="{{ $b64 }}" alt="{{ $name }}" />
                <div class="chart-caption">{{ ucwords(str_replace('_', ' ', $name)) }}</div>
            </div>
        @endforeach
    @endif

    @if (!empty($snapshot['fees']['top_due_students'] ?? []))
        <h2>Top Fee Defaulters</h2>
        <table class="dues">
            <thead>
                <tr><th>Student</th><th>Outstanding (₹)</th></tr>
            </thead>
            <tbody>
                @foreach ($snapshot['fees']['top_due_students'] as $r)
                    <tr>
                        <td>{{ $r['name'] ?? '' }}</td>
                        <td>₹{{ number_format($r['due'] ?? 0, 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (!empty($snapshot['attendance']['low_attendance_students'] ?? []))
        <h2>Low Attendance Students (last 30 days)</h2>
        <table class="dues">
            <thead>
                <tr><th>Student</th><th>Attendance %</th></tr>
            </thead>
            <tbody>
                @foreach ($snapshot['attendance']['low_attendance_students'] as $r)
                    <tr>
                        <td>{{ $r['name'] ?? '' }}</td>
                        <td>{{ $r['percentage'] ?? 0 }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
