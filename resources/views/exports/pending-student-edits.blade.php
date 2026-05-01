<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Pending Student Profile Edits</title>
<style>
    @page { margin: 10mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; color: #1A237E; margin: 0; padding: 0; font-size: 9pt; }

    .header {
        text-align: center; padding: 4mm 0 3mm; border-bottom: 1.5px solid #6A1B9A;
        margin-bottom: 4mm;
    }
    .header-school { font-size: 13pt; font-weight: 800; }
    .header-title  { font-size: 10pt; color: #6A1B9A; margin-top: 1mm; font-weight: 700; }
    .header-meta   { font-size: 8pt; color: #607D8B; margin-top: 1.5mm; }

    table.data {
        width: 100%; border-collapse: collapse; font-size: 8pt;
    }
    table.data th {
        background: #6A1B9A; color: white;
        padding: 2mm 1.5mm; text-align: left;
        font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.4px;
        border: 1px solid #4A148C;
    }
    table.data td {
        padding: 1.5mm; vertical-align: top;
        border: 1px solid #E1BEE7;
        word-wrap: break-word;
    }
    table.data tr:nth-child(even) td { background: #FAF5FB; }

    .field-cell { font-weight: 700; color: #4A148C; }
    .old-cell   { color: #B71C1C; }
    .new-cell   { color: #1B5E20; font-weight: 600; }

    .empty {
        text-align: center; padding: 12mm; color: #90A4AE;
        font-style: italic;
    }

    .footer {
        margin-top: 4mm; padding-top: 2mm;
        border-top: 1px dashed #ECEFF1;
        font-size: 7pt; color: #B0BEC5; text-align: center;
    }
</style>
</head>
<body>

<div class="header">
    <div class="header-school">{{ $school?->name ?? 'School' }}</div>
    <div class="header-title">Pending Student Profile Edits — for verification</div>
    <div class="header-meta">
        @if ($class)Class: <strong>{{ $class }}</strong> @endif
        @if ($section)&nbsp;&middot;&nbsp; Section: <strong>{{ $section }}</strong> @endif
        @if (! $class && ! $section)All classes &amp; sections @endif
        &nbsp;&middot;&nbsp; Generated: {{ $printed }}
        &nbsp;&middot;&nbsp; {{ $count }} pending change{{ $count === 1 ? '' : 's' }}
    </div>
</div>

@if ($count === 0)
    <div class="empty">No pending student edit requests.</div>
@else
    <table class="data">
        <thead>
            <tr>
                <th style="width: 8%">Admission No</th>
                <th style="width: 12%">Student</th>
                <th style="width: 7%">Class</th>
                <th style="width: 6%">Section</th>
                <th style="width: 9%">Field</th>
                <th style="width: 16%">Old Value</th>
                <th style="width: 16%">New Value</th>
                <th style="width: 9%">Requested By</th>
                <th style="width: 9%">Requested At</th>
                <th style="width: 8%">Reason</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td>{{ $r['admission_no'] }}</td>
                    <td>{{ $r['student_name'] }}</td>
                    <td>{{ $r['class'] }}</td>
                    <td>{{ $r['section'] }}</td>
                    <td class="field-cell">{{ $r['field'] }}</td>
                    <td class="old-cell">{{ $r['old_value'] !== '' ? $r['old_value'] : '—' }}</td>
                    <td class="new-cell">{{ $r['new_value'] !== '' ? $r['new_value'] : '—' }}</td>
                    <td>{{ $r['requested_by'] }}</td>
                    <td>{{ $r['requested_at'] }}</td>
                    <td>{{ $r['reason'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    Approve or reject these requests at /school/edit-requests
</div>

</body>
</html>
