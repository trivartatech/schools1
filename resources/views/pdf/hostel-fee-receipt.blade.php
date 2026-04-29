<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Hostel Fee Receipt - {{ $payment->receipt_no }}</title>
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

    <div class="receipt-title">HOSTEL FEE RECEIPT</div>

    @include('pdf._receipt-student-info')

    <table class="details-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Hostel / Room / Bed</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Hostel Fee</td>
                <td>
                    {{ optional($payment->allocation?->bed?->room?->hostel)->name ?? '—' }}
                    @if($payment->allocation?->bed?->room?->room_number)
                        <br><small style="color:#666;">Room: {{ $payment->allocation->bed->room->room_number }}@if($payment->allocation?->bed?->name) · {{ $payment->allocation->bed->name }} @endif</small>
                    @endif
                </td>
                <td class="text-right">{{ $school->currency ?? '₹' }}{{ number_format($payment->amount_paid, 2) }}</td>
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
            <tr class="total-row">
                <td colspan="2" class="text-right">Amount Received</td>
                <td class="text-right">{{ $school->currency ?? '₹' }}{{ number_format($payment->amount_paid, 2) }}</td>
            </tr>
            @if($payment->allocation)
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
