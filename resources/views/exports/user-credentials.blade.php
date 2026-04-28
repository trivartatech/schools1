<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    @page { margin: 18mm 14mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #263238; }

    .header { border-bottom: 2px solid #1565C0; padding-bottom: 10px; margin-bottom: 14px; }
    .school-name { font-size: 16px; font-weight: 800; color: #1A237E; }
    .doc-title { font-size: 13px; font-weight: 700; color: #1565C0; margin-top: 2px; letter-spacing: 0.5px; }
    .doc-meta { font-size: 9px; color: #78909C; margin-top: 6px; }

    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    thead th {
        background: #1565C0; color: #fff; font-size: 10px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.5px;
        padding: 8px 10px; text-align: left;
    }
    tbody td {
        padding: 7px 10px; border-bottom: 1px solid #ECEFF1;
        vertical-align: middle;
    }
    tbody tr:nth-child(even) td { background: #F5F7FA; }

    .role-badge {
        display: inline-block; padding: 2px 7px; border-radius: 3px;
        background: #E3F2FD; color: #1565C0; font-weight: 700;
        font-size: 9px; text-transform: uppercase; letter-spacing: 0.4px;
    }
    .username, .password { font-family: DejaVu Sans Mono, monospace; font-weight: 700; }
    .password { color: #2E7D32; }

    .footer-note {
        margin-top: 18px; padding: 10px 12px;
        background: #FFF3E0; border-left: 3px solid #E65100;
        font-size: 10px; color: #5D4037; line-height: 1.5;
    }
</style>
</head>
<body>

<div class="header">
    <div class="school-name">{{ $school?->name ?? 'School' }}</div>
    <div class="doc-title">{{ $title }}</div>
    <div class="doc-meta">
        Generated: {{ $printed }}
        &nbsp;·&nbsp; {{ count($rows) }} account{{ count($rows) === 1 ? '' : 's' }}
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 3%;">#</th>
            <th style="width: 26%;">Name</th>
            <th style="width: 11%;">Role</th>
            <th style="width: 12%;">Class</th>
            <th style="width: 8%;">Section</th>
            <th style="width: 18%;">Username</th>
            <th style="width: 22%;">Password</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $i => $row)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $row['name'] ?? '—' }}</td>
            <td><span class="role-badge">{{ $row['role'] ?? '—' }}</span></td>
            <td>{{ $row['class_name']   ?? '—' }}</td>
            <td>{{ $row['section_name'] ?? '—' }}</td>
            <td class="username">{{ $row['username'] ?? '—' }}</td>
            <td class="password">{{ $row['password'] ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="footer-note">
    <strong>Confidential.</strong> Distribute these credentials to each user via a secure channel
    (in person or SMS). Ask the user to change their password on first login. Do not store this
    document on shared drives or send it over unencrypted email.
</div>

</body>
</html>
