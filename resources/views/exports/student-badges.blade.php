<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    @page { margin: 10mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; color: #1A237E; margin: 0; padding: 0; }

    .header {
        text-align: center; padding: 6mm 0 4mm; border-bottom: 1.5px solid #6A1B9A;
        margin-bottom: 6mm;
    }
    .header-school { font-size: 14pt; font-weight: 800; }
    .header-meta   { font-size: 9pt; color: #607D8B; margin-top: 2mm; }

    /* 2 columns × 4 rows = 8 badges per A4 page */
    .grid { width: 100%; }
    .grid td { width: 50%; padding: 3mm; vertical-align: top; }

    .badge {
        border: 1.5px solid #6A1B9A; border-radius: 6px;
        padding: 4mm; height: 55mm;
        background: #fff;
    }
    .badge-top {
        border-bottom: 1px dashed #E1BEE7; padding-bottom: 2mm; margin-bottom: 3mm;
    }
    .badge-school { font-size: 9pt; font-weight: 700; color: #6A1B9A; }
    .badge-tag    { font-size: 6pt; color: #90A4AE; letter-spacing: 1px; text-transform: uppercase; }

    .badge-row { width: 100%; }
    .badge-row td { vertical-align: top; padding: 0; }
    .badge-info-cell { width: 60%; padding-right: 3mm !important; }
    .badge-qr-cell   { width: 40%; text-align: right; }
    .badge-qr-cell img { width: 26mm; height: 26mm; }

    .badge-name        { font-size: 11pt; font-weight: 800; color: #4A148C; }
    .badge-class       { font-size: 8pt; color: #455A64; margin-top: 1.5mm; }
    .badge-roll        { font-size: 7.5pt; color: #78909C; margin-top: 1mm; }
    .badge-admno {
        font-size: 7pt; font-weight: 700; color: #6A1B9A;
        background: #F3E5F5; display: inline-block;
        padding: 1mm 2.5mm; border-radius: 2mm; margin-top: 2mm;
        font-family: DejaVu Sans Mono, monospace;
    }

    .footer-strip {
        margin-top: 2mm; padding-top: 1.5mm;
        border-top: 1px dashed #ECEFF1;
        font-size: 6.5pt; color: #B0BEC5; text-align: center;
    }

    .pagebreak { page-break-after: always; }
</style>
</head>
<body>

<div class="header">
    <div class="header-school">{{ $school?->name ?? 'School' }}</div>
    <div class="header-meta">Student ID Badges &nbsp;·&nbsp; Generated {{ $printed }} &nbsp;·&nbsp; {{ count($rows) }} badge{{ count($rows) === 1 ? '' : 's' }}</div>
</div>

@php $cells = collect($rows)->chunk(2); @endphp

<table class="grid" cellpadding="0" cellspacing="0">
@foreach ($cells->chunk(4) as $pageIndex => $page)
    @foreach ($page as $row)
        <tr>
        @foreach ($row as $r)
            <td>
                <div class="badge">
                    <div class="badge-top">
                        <div class="badge-school">{{ $school?->name ?? 'School' }}</div>
                        <div class="badge-tag">Student ID</div>
                    </div>
                    <table class="badge-row" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="badge-info-cell">
                                <div class="badge-name">{{ $r['name'] ?? '—' }}</div>
                                <div class="badge-class">
                                    {{ collect([$r['class'] ?? null, $r['section'] ?? null])->filter()->implode(' · ') ?: '—' }}
                                </div>
                                @if (!empty($r['roll_no']))
                                    <div class="badge-roll">Roll No: {{ $r['roll_no'] }}</div>
                                @endif
                                <div class="badge-admno">{{ $r['admission_no'] ?? '—' }}</div>
                            </td>
                            <td class="badge-qr-cell">
                                @if (!empty($r['qr_data_uri']))
                                    <img src="{{ $r['qr_data_uri'] }}" />
                                @endif
                            </td>
                        </tr>
                    </table>
                    <div class="footer-strip">Scan to mark attendance</div>
                </div>
            </td>
        @endforeach
        @if ($row->count() === 1)
            <td></td>
        @endif
        </tr>
    @endforeach
    @if (!$loop->last)
        <tr><td colspan="2" class="pagebreak"></td></tr>
    @endif
@endforeach
</table>

</body>
</html>
