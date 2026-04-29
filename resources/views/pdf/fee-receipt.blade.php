<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fee Receipt - {{ $payment->receipt_no }}</title>
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

    <div class="receipt-title">FEE RECEIPT</div>

    @include('pdf._receipt-student-info')

    <table class="details-table">
        <thead>
            <tr>
                <th>Fee Head</th>
                <th>Term</th>
                <th class="text-right">Amount Due</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $payment->feeHead->name }}<br><small style="color:#666; font-weight:normal;">{{ $payment->feeHead->feeGroup->name }}</small></td>
                <td>{{ str_replace('_', ' ', ucfirst($payment->term)) }}</td>
                <td class="text-right">{{ $school->currency ?? '₹' }}{{ number_format($payment->amount_due, 2) }}</td>
            </tr>
            @if($payment->fine > 0)
            <tr>
                <td colspan="2" class="text-right"><strong>Fine / Late Fee</strong></td>
                <td class="text-right">{{ $school->currency ?? '₹' }}{{ number_format($payment->fine, 2) }}</td>
            </tr>
            @endif
            @if($payment->discount > 0)
            <tr>
                <td colspan="2" class="text-right">
                    <strong>Discount / Concession</strong>
                    @if($payment->concession_note)
                        <br><small style="color:#666; font-weight:normal;">({{ $payment->concession_note }})</small>
                    @endif
                </td>
                <td class="text-right">- {{ $school->currency ?? '₹' }}{{ number_format($payment->discount, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td colspan="2" class="text-right">Amount Received</td>
                <td class="text-right">{{ $school->currency ?? '₹' }}{{ number_format($payment->amount_paid, 2) }}</td>
            </tr>
            @if($payment->tax_amount > 0)
            <tr>
                <td colspan="2" class="text-right">
                    <small style="color:#666;">Includes GST ({{ number_format($payment->tax_percent, 1) }}%)</small><br>
                    <small style="color:#666;">Base: {{ $school->currency ?? '₹' }}{{ number_format($payment->taxable_amount, 2) }} | 
                    CGST: {{ $school->currency ?? '₹' }}{{ number_format($payment->tax_amount / 2, 2) }} | 
                    SGST: {{ $school->currency ?? '₹' }}{{ number_format($payment->tax_amount / 2, 2) }}</small>
                </td>
                <td class="text-right">
                    <small style="color:#666;">{{ $school->currency ?? '₹' }}{{ number_format($payment->tax_amount, 2) }}</small>
                </td>
            </tr>
            @endif
            <tr>
                <td colspan="2" class="text-right" style="color:#666;">Balance Remaining</td>
                <td class="text-right" style="color:#666;">{{ $school->currency ?? '₹' }}{{ number_format($payment->balance, 2) }}</td>
            </tr>
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
