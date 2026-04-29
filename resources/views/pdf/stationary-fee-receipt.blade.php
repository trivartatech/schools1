<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stationary Fee Receipt - {{ $payment->receipt_no }}</title>
    @include('pdf._receipt-styles')
</head>
<body class="copies-{{ count($copyLabels ?? ['Original']) }}">
@foreach (($copyLabels ?? ['Original']) as $copyIndex => $copyLabel)
@if(!$loop->first)
    <div class="cut-line">- - - - - - - - - - - - - - - cut here - - - - - - - - - - - - - - -</div>
@endif
<div class="copy-page">
    <div class="copy-label-bar"><span class="copy-label">{{ $copyLabel }}</span></div>

    @include('pdf._receipt-header')

    <div class="receipt-title">STATIONARY FEE RECEIPT</div>

    @include('pdf._receipt-student-info')

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
</div>
@endforeach
</body>
</html>
