<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Bus Pass</title>
<style>
    @page { margin: 10mm; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; margin: 0; padding: 0; color: #1a1a2e; }

    /* ── Outer grid ─────────────────────────────────────────────────
       A4 usable area after 10mm margins: ~190mm × 277mm
       Card size: CR80 = 85.6mm × 54mm
       2 cols × 4 rows = 8 cards per page, 3mm gutters
    ─────────────────────────────────────────────────────────────── */
    .grid { width: 100%; border-collapse: collapse; }
    .grid td {
        width: 50%;
        padding: 1.5mm;
        vertical-align: top;
    }

    /* ── Card shell ─────────────────────────────────────────────── */
    .card {
        width: 85.6mm;
        height: 54mm;
        border: 1px solid #90caf9;
        border-radius: 3mm;
        overflow: hidden;
        background: #ffffff;
        page-break-inside: avoid;
    }

    /* ── Blue header strip ──────────────────────────────────────── */
    .card-header {
        background: #1565c0;
        color: #ffffff;
        padding: 1.5mm 2mm;
        display: table;
        width: 100%;
    }
    .card-header-logo-cell {
        display: table-cell;
        vertical-align: middle;
        width: 9mm;
        padding-right: 1.5mm;
    }
    .card-header-logo-cell img { height: 7mm; width: auto; }
    .card-header-text-cell {
        display: table-cell;
        vertical-align: middle;
    }
    .card-school-name {
        font-size: 5.5pt;
        font-weight: bold;
        line-height: 1.2;
        letter-spacing: 0.2px;
        text-transform: uppercase;
    }
    .card-header-badge-cell {
        display: table-cell;
        vertical-align: middle;
        text-align: right;
        white-space: nowrap;
    }
    .bus-pass-label {
        font-size: 6pt;
        font-weight: 900;
        letter-spacing: 1px;
        text-transform: uppercase;
        border: 1px solid rgba(255,255,255,0.6);
        padding: 0.5mm 2mm;
        border-radius: 1mm;
        background: rgba(255,255,255,0.15);
    }

    /* ── Card body ──────────────────────────────────────────────── */
    .card-body {
        padding: 1.5mm 2mm 1mm 2mm;
        display: table;
        width: 100%;
        height: 40mm;  /* 54mm total − ~12mm header − 2mm footer */
    }
    .card-info-cell {
        display: table-cell;
        vertical-align: top;
        width: 64%;
        padding-right: 1.5mm;
    }
    .card-qr-cell {
        display: table-cell;
        vertical-align: top;
        width: 36%;
        text-align: right;
    }
    .card-qr-cell img { width: 22mm; height: 22mm; }
    .qr-hint { font-size: 4.5pt; color: #888; text-align: center; margin-top: 0.5mm; }

    /* Student name */
    .student-name {
        font-size: 7.5pt;
        font-weight: bold;
        color: #0d47a1;
        line-height: 1.2;
        margin-bottom: 1mm;
    }
    /* Class + Adm row */
    .meta-row {
        font-size: 5.5pt;
        color: #37474f;
        margin-bottom: 0.8mm;
        line-height: 1.3;
    }
    .meta-label { color: #78909c; }

    /* Divider */
    .divider {
        border: none;
        border-top: 0.5px dashed #b0bec5;
        margin: 1mm 0;
    }

    /* Transport details */
    .detail-row {
        font-size: 5.5pt;
        color: #263238;
        line-height: 1.4;
        margin-bottom: 0.5mm;
    }
    .detail-label { color: #78909c; }

    /* ── Footer strip ───────────────────────────────────────────── */
    .card-footer {
        border-top: 0.5px solid #e3f2fd;
        padding: 0.8mm 2mm;
        display: table;
        width: 100%;
        background: #e3f2fd;
    }
    .card-footer-left {
        display: table-cell;
        font-size: 4.5pt;
        color: #546e7a;
        vertical-align: middle;
    }
    .card-footer-right {
        display: table-cell;
        text-align: right;
        vertical-align: middle;
    }
    .status-active   { font-size: 4.5pt; font-weight: bold; color: #2e7d32; background: #e8f5e9; padding: 0.3mm 1.5mm; border-radius: 1mm; }
    .status-inactive { font-size: 4.5pt; font-weight: bold; color: #757575; background: #f5f5f5; padding: 0.3mm 1.5mm; border-radius: 1mm; }

    /* ── Page break ─────────────────────────────────────────────── */
    .page-break { page-break-after: always; }
</style>
</head>
<body>

@php
    // Embed school logo as base64 for DomPDF
    $logoData = null;
    if (!empty($school->logo)) {
        $logoFsPath = storage_path('app/public/' . ltrim($school->logo, '/'));
        if (is_file($logoFsPath)) {
            $mime = function_exists('mime_content_type')
                ? (mime_content_type($logoFsPath) ?: 'image/png')
                : 'image/png';
            $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoFsPath));
        }
    }

    // 8 cards per page (2 cols × 4 rows)
    $pages = collect($passes)->chunk(8);
@endphp

@foreach ($pages as $pageIndex => $pageChunk)
    @if (!$loop->first)
        <div class="page-break"></div>
    @endif

    @php $rows = $pageChunk->chunk(2); @endphp

    <table class="grid" cellpadding="0" cellspacing="0">
    @foreach ($rows as $row)
        <tr>
        @foreach ($row as $pass)
            <td>
                <div class="card">

                    {{-- ── Blue header strip ─────────────────────────────────── --}}
                    <div class="card-header">
                        <table style="width:100%;border-collapse:collapse;">
                            <tr>
                                @if($logoData)
                                <td class="card-header-logo-cell">
                                    <img src="{{ $logoData }}" alt="">
                                </td>
                                @endif
                                <td class="card-header-text-cell">
                                    <div class="card-school-name">{{ $school->name }}</div>
                                </td>
                                <td class="card-header-badge-cell">
                                    <span class="bus-pass-label">Bus Pass</span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    {{-- ── Card body ─────────────────────────────────────────── --}}
                    <div class="card-body">
                        <table style="width:100%;border-collapse:collapse;height:100%;">
                            <tr>
                                <td class="card-info-cell">

                                    {{-- Student name --}}
                                    <div class="student-name">{{ $pass['name'] }}</div>

                                    {{-- Class / Admission --}}
                                    @if($pass['classSection'])
                                    <div class="meta-row">
                                        <span class="meta-label">Class: </span>{{ $pass['classSection'] }}
                                    </div>
                                    @endif
                                    <div class="meta-row">
                                        <span class="meta-label">Adm#: </span>
                                        <strong>{{ $pass['admNo'] }}</strong>
                                    </div>

                                    <hr class="divider">

                                    {{-- Route & Stop --}}
                                    <div class="detail-row">
                                        <span class="detail-label">Route: </span>{{ $pass['routeName'] }}
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Stop: </span>{{ $pass['stopName'] }}
                                        @if($pass['vehicleNo'] !== '—')
                                            &nbsp;|&nbsp;<span class="detail-label">Veh: </span>{{ $pass['vehicleNo'] }}
                                        @endif
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Type: </span>{{ $pass['pickupType'] }}
                                    </div>
                                    @if($pass['validFrom'] || $pass['validTo'])
                                    <div class="detail-row">
                                        <span class="detail-label">Valid: </span>
                                        @if($pass['validFrom'] && $pass['validTo'])
                                            {{ $pass['validFrom'] }} – {{ $pass['validTo'] }}
                                        @elseif($pass['validFrom'])
                                            From {{ $pass['validFrom'] }}
                                        @else
                                            Open-ended
                                        @endif
                                    </div>
                                    @endif

                                </td>

                                {{-- QR code --}}
                                <td class="card-qr-cell">
                                    @if(!empty($pass['qrCode']))
                                        <img src="data:image/svg+xml;base64,{{ $pass['qrCode'] }}" alt="QR">
                                        <div class="qr-hint">Scan to verify</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>

                    {{-- ── Footer strip ──────────────────────────────────────── --}}
                    <div class="card-footer">
                        <table style="width:100%;border-collapse:collapse;">
                            <tr>
                                <td class="card-footer-left">
                                    ________________________<br>
                                    <span style="font-size:4pt;">Transport In-charge</span>
                                </td>
                                <td class="card-footer-right">
                                    @if(($pass['status'] ?? 'active') === 'active')
                                        <span class="status-active">● ACTIVE</span>
                                    @else
                                        <span class="status-inactive">● INACTIVE</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>

                </div>{{-- .card --}}
            </td>
        @endforeach
        {{-- Pad with empty cell if odd number in last row --}}
        @if($row->count() === 1)
            <td></td>
        @endif
        </tr>
    @endforeach
    </table>

@endforeach

</body>
</html>
