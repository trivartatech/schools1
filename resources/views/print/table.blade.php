<!DOCTYPE html>
<html>
<head>
    <title>{{ ucwords($title ?? 'Export') }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0 auto;
            padding: 20px;
            max-width: 1200px;
            color: #1f2937;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #1f2937;
        }

        .header h1 {
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            margin: 0 0 4px 0;
        }

        .header .timestamp {
            font-size: 10px;
            color: #6b7280;
            font-style: italic;
        }

        table.export-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #888a8e;
        }

        table.export-table thead {
            background-color: #e9ecf1;
        }

        table.export-table th {
            font-weight: bold;
            text-align: left;
            padding: 8px 10px;
            font-size: 11px;
            border: 1px solid #888a8e;
        }

        table.export-table td {
            padding: 5px 10px;
            border: 1px solid #d1d5db;
            font-size: 11px;
        }

        table.export-table tr:nth-child(even) {
            background-color: rgba(0, 0, 0, 0.03);
        }

        table.export-table tfoot td {
            font-weight: bold;
            padding: 8px 10px;
            background-color: #e9ecf1;
            border: 1px solid #888a8e;
        }

        .footer {
            margin-top: 16px;
            font-size: 10px;
            color: #6b7280;
            text-align: right;
            font-style: italic;
        }

        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ ucwords($title ?? 'Export') }}</h1>
        @if (!empty($generatedAt))
            <div class="timestamp">Generated on {{ $generatedAt }}</div>
        @endif
    </div>

    <table class="export-table">
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        @if (!empty($footerRow))
            <tfoot>
                <tr>
                    @foreach ($footerRow as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer">
        Total Records: {{ count($rows) }}
        @if (!empty($generatedAt))
            &bull; {{ $generatedAt }}
        @endif
    </div>
</body>
</html>
