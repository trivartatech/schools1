<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Transport Routes &amp; Stops</title>
    <style>
        @page { margin: 12mm 12mm 14mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a2e; margin: 0; padding: 0; }

        /* ── Header ─────────────────────────────────────────────────── */
        .header { border-bottom: 2px solid #1565c0; padding-bottom: 8px; margin-bottom: 10px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-logo-cell { width: 70px; vertical-align: middle; text-align: left; }
        .header-logo-cell img { height: 60px; width: auto; }
        .header-text-cell { vertical-align: middle; padding-left: 10px; }
        .school-name { font-size: 17px; font-weight: bold; text-transform: uppercase; color: #0d47a1; line-height: 1.2; }
        .school-sub { font-size: 9px; color: #555; margin-top: 2px; }
        .doc-title-cell { vertical-align: middle; text-align: right; }
        .doc-title { font-size: 13px; font-weight: bold; color: #1565c0; text-transform: uppercase; letter-spacing: 0.5px; }
        .doc-meta { font-size: 8.5px; color: #777; margin-top: 3px; }

        /* ── Summary bar ─────────────────────────────────────────────── */
        .summary-bar {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 4px;
            padding: 5px 10px;
            margin-bottom: 12px;
            font-size: 9.5px;
            color: #1565c0;
        }
        .summary-bar span { margin-right: 18px; font-weight: bold; }
        .summary-bar .label { font-weight: normal; color: #555; }

        /* ── Route block ─────────────────────────────────────────────── */
        .route-block { margin-bottom: 14px; page-break-inside: avoid; }

        .route-header {
            background: #1565c0;
            color: #fff;
            padding: 5px 10px;
            border-radius: 3px 3px 0 0;
        }
        .route-header-table { width: 100%; border-collapse: collapse; }
        .route-name { font-size: 11px; font-weight: bold; }
        .route-code { font-size: 9px; color: #bbdefb; margin-top: 1px; }
        .route-meta { font-size: 9px; color: #e3f2fd; }
        .badge {
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            padding: 1px 6px;
            border-radius: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-active   { background: #e8f5e9; color: #2e7d32; }
        .badge-inactive { background: #f5f5f5; color: #757575; }

        /* ── Stop table ──────────────────────────────────────────────── */
        .stop-table { width: 100%; border-collapse: collapse; border: 1px solid #ddd; border-top: none; border-radius: 0 0 3px 3px; }
        .stop-table thead tr { background: #f0f4ff; }
        .stop-table th {
            padding: 5px 8px;
            font-size: 9px;
            font-weight: bold;
            color: #333;
            text-align: left;
            border-bottom: 1px solid #c5cae9;
            border-right: 1px solid #e8eaf6;
        }
        .stop-table th:last-child { border-right: none; }
        .stop-table td {
            padding: 5px 8px;
            font-size: 10px;
            color: #333;
            border-bottom: 1px solid #eee;
            border-right: 1px solid #f5f5f5;
            vertical-align: top;
        }
        .stop-table td:last-child { border-right: none; }
        .stop-table tr:last-child td { border-bottom: none; }
        .stop-table tr:nth-child(even) td { background: #fafafa; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-muted { color: #888; }
        .text-bold { font-weight: bold; }
        .fee-value { color: #1b5e20; font-weight: bold; }
        .no-fee { color: #aaa; }

        /* ── No stops row ────────────────────────────────────────────── */
        .no-stops { text-align: center; color: #999; font-style: italic; padding: 8px; border: 1px solid #ddd; border-top: none; }

        /* ── Total row ───────────────────────────────────────────────── */
        .total-row td { background: #e8f5e9 !important; font-weight: bold; color: #1b5e20; }

        /* ── Footer ──────────────────────────────────────────────────── */
        .page-footer { margin-top: 16px; border-top: 1px solid #ddd; padding-top: 5px; font-size: 8.5px; color: #888; display: table; width: 100%; }
        .footer-left { display: table-cell; text-align: left; }
        .footer-right { display: table-cell; text-align: right; }
    </style>
</head>
<body>

@php
    /* ── Logo ── */
    $logoData = null;
    if (!empty($school->logo)) {
        $path = storage_path('app/public/' . ltrim($school->logo, '/'));
        if (is_file($path)) {
            $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/png') : 'image/png';
            $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
        }
    }

    /* ── Address ── */
    $address = trim(implode(', ', array_filter([
        $school->settings['address_line1'] ?? $school->address ?? null,
        $school->city    ?? null,
        $school->state   ?? null,
        $school->pincode ?? null,
    ])));

    $currency = $school->currency ?? '₹';

    /* ── Summary counts ── */
    $totalRoutes = $routes->count();
    $totalStops  = $routes->sum(fn($r) => $r->stops->count());
    $activeRoutes = $routes->where('status', 'active')->count();
@endphp

{{-- ════════════════════════════════════════ HEADER ════════════════════════════════════════ --}}
<div class="header">
    <table class="header-table">
        <tr>
            @if($logoData)
            <td class="header-logo-cell">
                <img src="{{ $logoData }}" alt="">
            </td>
            @endif
            <td class="header-text-cell">
                <div class="school-name">{{ $school->name }}</div>
                @if($address)
                    <div class="school-sub">{{ $address }}</div>
                @endif
                @if(!empty($school->phone))
                    <div class="school-sub">Phone: {{ $school->phone }}</div>
                @endif
            </td>
            <td class="doc-title-cell">
                <div class="doc-title">Transport Routes &amp; Stops</div>
                <div class="doc-meta">Generated: {{ $generatedAt }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ════════════════════════════════════════ SUMMARY ════════════════════════════════════════ --}}
<div class="summary-bar">
    <span><span class="label">Total Routes: </span>{{ $totalRoutes }}</span>
    <span><span class="label">Active: </span>{{ $activeRoutes }}</span>
    <span><span class="label">Total Stops: </span>{{ $totalStops }}</span>
</div>

{{-- ════════════════════════════════════════ ROUTES ════════════════════════════════════════ --}}
@forelse ($routes as $route)
@php
    $stops        = $route->stops;
    $vehicles     = $route->vehicles;
    $vehicleLabel = $vehicles->isNotEmpty()
        ? $vehicles->map(fn($v) => $v->vehicle_number . ($v->vehicle_name ? ' (' . $v->vehicle_name . ')' : ''))->implode(', ')
        : null;
    $stopFeeTotal = $stops->sum('fee');
@endphp

<div class="route-block">

    {{-- Route header bar --}}
    <div class="route-header">
        <table class="route-header-table">
            <tr>
                <td>
                    <div class="route-name">{{ $route->route_name }}</div>
                    <div class="route-code">Code: {{ $route->route_code }}</div>
                </td>
                <td class="route-meta" style="text-align:center; width:35%;">
                    @if($route->start_location || $route->end_location)
                        {{ $route->start_location ?? '—' }} → {{ $route->end_location ?? '—' }}
                    @endif
                    @if($route->distance)
                        <br>{{ number_format($route->distance, 1) }} km
                    @endif
                    @if($route->estimated_time)
                        &nbsp;|&nbsp; ~{{ $route->estimated_time }}
                    @endif
                </td>
                <td style="text-align:right; width:30%;">
                    @if($vehicleLabel)
                        <div class="route-meta">🚌 {{ $vehicleLabel }}</div>
                    @endif
                    <div style="margin-top:3px;">
                        @if($route->status === 'active')
                            <span class="badge badge-active">Active</span>
                        @else
                            <span class="badge badge-inactive">Inactive</span>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Stops table --}}
    @if($stops->isNotEmpty())
    <table class="stop-table">
        <thead>
            <tr>
                <th style="width:6%;" class="text-center">#</th>
                <th style="width:28%;">Stop Name</th>
                <th style="width:10%;">Code</th>
                <th style="width:13%;">Pickup Time</th>
                <th style="width:13%;">Drop Time</th>
                <th style="width:15%;">Distance (km)</th>
                <th style="width:15%;" class="text-right">Stop Fee</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($stops as $stop)
            <tr>
                <td class="text-center text-muted">{{ $loop->iteration }}</td>
                <td class="text-bold">{{ $stop->stop_name }}</td>
                <td class="text-muted">{{ $stop->stop_code ?? '—' }}</td>
                <td>{{ $stop->pickup_time ? \Carbon\Carbon::parse($stop->pickup_time)->format('h:i A') : '—' }}</td>
                <td>{{ $stop->drop_time   ? \Carbon\Carbon::parse($stop->drop_time)->format('h:i A')   : '—' }}</td>
                <td class="text-center">
                    {{ $stop->distance_from_school ? number_format($stop->distance_from_school, 1) : '—' }}
                </td>
                <td class="text-right">
                    @if($stop->fee > 0)
                        <span class="fee-value">{{ $currency }}{{ number_format($stop->fee, 2) }}</span>
                    @else
                        <span class="no-fee">—</span>
                    @endif
                </td>
            </tr>
            @endforeach

            {{-- Total fee row --}}
            @if($stopFeeTotal > 0)
            <tr class="total-row">
                <td colspan="6" class="text-right">Total Stop Fee</td>
                <td class="text-right">{{ $currency }}{{ number_format($stopFeeTotal, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    @else
    <div class="no-stops">No stops defined for this route.</div>
    @endif

</div>
@empty
<p style="text-align:center; color:#999; margin-top:40px;">No transport routes found.</p>
@endforelse

{{-- ════════════════════════════════════════ FOOTER ════════════════════════════════════════ --}}
<div class="page-footer">
    <div class="footer-left">{{ $school->name }} — Transport Routes &amp; Stop Fee Schedule</div>
    <div class="footer-right">{{ $generatedAt }}</div>
</div>

</body>
</html>
