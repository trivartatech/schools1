<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Photo Numbers Roster</title>
<style>
    @page { margin: 8mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; color: #1A237E; margin: 0; padding: 0; font-size: 8pt; }

    .header {
        text-align: center; padding: 4mm 0 3mm; border-bottom: 1.5px solid #6A1B9A;
        margin-bottom: 4mm;
    }
    .header-school { font-size: 13pt; font-weight: 800; }
    .header-title  { font-size: 10pt; color: #6A1B9A; margin-top: 1mm; font-weight: 700; }
    .header-meta   { font-size: 7.5pt; color: #607D8B; margin-top: 1.5mm; }

    table.data {
        width: 100%; border-collapse: collapse; font-size: 7.5pt;
    }
    table.data th {
        background: #6A1B9A; color: white;
        padding: 2mm 1.5mm; text-align: left;
        font-size: 7pt; text-transform: uppercase; letter-spacing: 0.3px;
        border: 1px solid #4A148C;
    }
    table.data td {
        padding: 1.5mm; vertical-align: top;
        border: 1px solid #E1BEE7;
        word-wrap: break-word;
    }
    table.data tr:nth-child(even) td { background: #FAF5FB; }

    .photo-no  { font-family: DejaVu Sans Mono, monospace; font-weight: 700; color: #4A148C; text-align: center; }
    .name      { font-weight: 600; }
    .pending   { color: #92400e; font-weight: 700; }
    .empty-cell { color: #cbd5e1; }

    .footer {
        margin-top: 3mm; padding-top: 2mm;
        border-top: 1px dashed #ECEFF1;
        font-size: 7pt; color: #B0BEC5; text-align: center;
    }

    .empty {
        text-align: center; padding: 12mm; color: #90A4AE;
        font-style: italic;
    }
</style>
</head>
<body>

<div class="header">
    <div class="header-school">{{ $school?->name ?? 'School' }}</div>
    <div class="header-title">Photo Numbers Roster — for ID-card photoshoot</div>
    <div class="header-meta">
        @if ($class)Class: <strong>{{ $class }}</strong> @endif
        @if ($section)&nbsp;&middot;&nbsp; Section: <strong>{{ $section }}</strong> @endif
        @if (! $class && ! $section)All classes &amp; sections @endif
        &nbsp;&middot;&nbsp; Generated: {{ $printed }}
        &nbsp;&middot;&nbsp; {{ $count }} student{{ $count === 1 ? '' : 's' }}
    </div>
</div>

@if ($count === 0)
    <div class="empty">No students found for this filter.</div>
@else
    <table class="data">
        <thead>
            <tr>
                <th style="width: 7%">Adm. No</th>
                <th style="width: 6%">Photo #</th>
                <th style="width: 13%">Student</th>
                <th style="width: 5%">Class</th>
                <th style="width: 5%">Sec</th>
                <th style="width: 12%">Address</th>
                <th style="width: 7%">Primary</th>
                <th style="width: 10%">Father</th>
                <th style="width: 7%">Father Phone</th>
                <th style="width: 10%">Mother</th>
                <th style="width: 7%">Mother Phone</th>
                <th style="width: 11%">Parent Address</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td>{{ $r['admission_no'] }}</td>
                    <td class="photo-no">{{ $r['photo_number'] !== '' ? $r['photo_number'] : '—' }}</td>
                    <td class="name">
                        {{ $r['name'] }}
                        @if (($r['pending_changes_count'] ?? 0) > 0)
                            <br><span class="pending">⚠ {{ $r['pending_changes_count'] }} pending</span>
                        @endif
                    </td>
                    <td>{{ $r['class'] ?: '—' }}</td>
                    <td>{{ $r['section'] ?: '—' }}</td>
                    <td>{{ $r['student_address'] ?: '' }}</td>
                    <td>{{ $r['primary_phone'] ?: '' }}</td>
                    <td>{{ $r['father_name'] ?: '' }}</td>
                    <td>{{ $r['father_phone'] ?: '' }}</td>
                    <td>{{ $r['mother_name'] ?: '' }}</td>
                    <td>{{ $r['mother_phone'] ?: '' }}</td>
                    <td>{{ $r['parent_address'] ?: '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    Cross-check this sheet against the photographer's call-out during the session. Any corrections can be entered via the Edit pencil on the Photo Numbers page (changes go through admin approval).
</div>

</body>
</html>
