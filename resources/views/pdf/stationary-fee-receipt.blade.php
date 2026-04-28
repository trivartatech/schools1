<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stationary Fee Receipt - {{ $payment->receipt_no }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 13px; color: #333; margin: 0; padding: 10px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        .school-name { font-size: 24px; font-weight: bold; margin: 0; text-transform: uppercase; }
        .school-address { font-size: 12px; color: #666; margin-top: 5px; }
        .receipt-title { text-align: center; font-size: 18px; font-weight: bold; margin: 15px 0; text-decoration: underline; }
        .info-table, .details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 5px; vertical-align: top; }
        .label { font-weight: bold; color: #555; width: 30%; }
        .details-table th, .details-table td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
        .details-table th { background-color: #f8f9fa; font-weight: bold; }
        .details-table .text-right { text-align: right; }
        .total-row th, .total-row td { font-weight: bold; background-color: #f8f9fa; }
        .footer { margin-top: 40px; display: table; width: 100%; }
        .signature-box { display: table-cell; width: 50%; text-align: right; vertical-align: bottom; }
        .signature-line { display: inline-block; width: 200px; border-top: 1px solid #333; text-align: center; padding-top: 5px; }
        .qr-box { display: table-cell; width: 50%; vertical-align: bottom; }
        .qr-code { width: 100px; height: 100px; }
        .qr-text { font-size: 10px; color: #777; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="school-name">{{ $school->name }}</h1>
        @if($school->address)
            <div class="school-address">
                {{ $school->address }} <br>
                {{ $school->city }}, {{ $school->state }} - {{ $school->zip_code }}
            </div>
        @endif
        @if($school->email || $school->phone)
            <div class="school-address">
                @if($school->phone) Phone: {{ $school->phone }} @endif
                @if($school->email) | Email: {{ $school->email }} @endif
            </div>
        @endif
    </div>

    <div class="receipt-title">STATIONARY FEE RECEIPT</div>

    <table class="info-table">
        <tr>
            <td class="label">Receipt No:</td>
            <td><strong>{{ $payment->receipt_no }}</strong></td>
            <td class="label">Payment Date:</td>
            <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d-M-Y') }}</td>
        </tr>
        <tr>
            <td class="label">Student Name:</td>
            <td><strong>{{ $payment->student->first_name }} {{ $payment->student->last_name }}</strong></td>
            <td class="label">Admission No:</td>
            <td>{{ $payment->student->admission_no }}</td>
        </tr>
        <tr>
            <td class="label">Academic Year:</td>
            <td>{{ optional($payment->academicYear)->name ?? '—' }}</td>
            <td class="label">Payment Mode:</td>
            <td>{{ strtoupper($payment->payment_mode instanceof \App\Enums\PaymentMode ? $payment->payment_mode->value : $payment->payment_mode) }}</td>
        </tr>
        @if($payment->transaction_ref)
        <tr>
            <td class="label">Transaction Ref:</td>
            <td colspan="3">{{ $payment->transaction_ref }}</td>
        </tr>
        @endif
    </table>

    @if($payment->allocation?->lineItems?->count())
    <table class="details-table">
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-right">Qty (Entitled)</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payment->allocation->lineItems as $line)
            <tr>
                <td>{{ $line->item?->name ?? '—' }}<br><small style="color:#888;font-family:monospace;">{{ $line->item?->code }}</small></td>
                <td class="text-right">{{ $line->qty_entitled }}</td>
                <td class="text-right">{{ $school->currency ?? '₹' }}{{ number_format($line->unit_price, 2) }}</td>
                <td class="text-right">{{ $school->currency ?? '₹' }}{{ number_format($line->line_total, 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3" class="text-right">Kit Total</td>
                <td class="text-right">{{ $school->currency ?? '₹' }}{{ number_format($payment->allocation->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    <table class="details-table">
        <tbody>
            <tr>
                <td colspan="2" class="text-right">Amount Received This Receipt</td>
                <td class="text-right" style="font-weight:bold;">{{ $school->currency ?? '₹' }}{{ number_format($payment->amount_paid, 2) }}</td>
            </tr>
            @if($payment->fine > 0)
            <tr>
                <td colspan="2" class="text-right"><strong>Fine / Late Fee</strong></td>
                <td class="text-right">{{ $school->currency ?? '₹' }}{{ number_format($payment->fine, 2) }}</td>
            </tr>
            @endif
            @if($payment->discount > 0)
            <tr>
                <td colspan="2" class="text-right"><strong>Discount / Concession</strong></td>
                <td class="text-right">- {{ $school->currency ?? '₹' }}{{ number_format($payment->discount, 2) }}</td>
            </tr>
            @endif
            @if($payment->allocation)
            <tr>
                <td colspan="2" class="text-right" style="color:#666;">Total Paid So Far</td>
                <td class="text-right" style="color:#666;">{{ $school->currency ?? '₹' }}{{ number_format($payment->allocation->amount_paid, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" class="text-right" style="color:#666;">Balance Remaining</td>
                <td class="text-right" style="color:#666;">{{ $school->currency ?? '₹' }}{{ number_format($payment->allocation->balance, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    @if($payment->remarks)
    <div style="margin-top: 10px; font-size: 13px;">
        <strong>Remarks:</strong> {{ $payment->remarks }}
    </div>
    @endif

    <div class="footer">
        <div class="qr-box">
            @if(isset($qrCode))
                <img src="data:image/svg+xml;base64,{{ $qrCode }}" class="qr-code" alt="QR Code">
                <div class="qr-text">Scan to verify<br>receipt authenticity</div>
            @endif
        </div>

        <div class="signature-box">
            <div class="signature-line">
                Authorized Signatory<br>
                <small>{{ $payment->collectedBy ? $payment->collectedBy->name : 'Administrator' }}</small>
            </div>
        </div>
    </div>
</body>
</html>
