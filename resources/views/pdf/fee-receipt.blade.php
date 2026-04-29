<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fee Receipt - {{ $payment->receipt_no }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 13px;
            color: #333;
            margin: 0;
            padding: 10px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .school-name {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }
        .school-address {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .receipt-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin: 15px 0;
            text-decoration: underline;
        }
        .info-table, .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table td {
            padding: 5px;
            vertical-align: top;
        }
        .label {
            font-weight: bold;
            color: #555;
            width: 30%;
        }
        .details-table th, .details-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .details-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .details-table .text-right {
            text-align: right;
        }
        .total-row th, .total-row td {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .footer {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        .signature-box {
            display: table-cell;
            width: 50%;
            text-align: right;
            vertical-align: bottom;
        }
        .signature-line {
            display: inline-block;
            width: 200px;
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 5px;
        }
        .qr-box {
            display: table-cell;
            width: 50%;
            vertical-align: bottom;
        }
        .qr-code {
            width: 100px;
            height: 100px;
        }
        .qr-text {
            font-size: 10px;
            color: #777;
            margin-top: 5px;
        }
        .copy-label-bar {
            text-align: right;
            margin-bottom: 4px;
        }
        .copy-label {
            display: inline-block;
            font-size: 10px;
            font-weight: bold;
            color: #555;
            border: 1px solid #999;
            padding: 2px 8px;
            border-radius: 3px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .school-logo {
            height: 60px;
            margin-bottom: 6px;
        }
        .cut-line {
            text-align: center;
            border-top: 1px dashed #999;
            margin: 10px 0;
            padding-top: 3px;
            color: #888;
            font-size: 10px;
            letter-spacing: 2px;
        }
        /* Compact mode: when more than 1 copy is requested, shrink everything so multiple copies fit on the same paper */
        body.multi-copy { font-size: 10px; padding: 5px; }
        body.multi-copy .school-name { font-size: 17px; }
        body.multi-copy .school-address { font-size: 10px; margin-top: 2px; }
        body.multi-copy .school-logo { height: 38px; margin-bottom: 3px; }
        body.multi-copy .header { padding-bottom: 8px; margin-bottom: 8px; }
        body.multi-copy .receipt-title { font-size: 13px; margin: 6px 0; }
        body.multi-copy .info-table { margin-bottom: 6px; }
        body.multi-copy .info-table td { padding: 2px 4px; }
        body.multi-copy .details-table { margin-bottom: 6px; }
        body.multi-copy .details-table th,
        body.multi-copy .details-table td { padding: 4px 6px; }
        body.multi-copy .footer { margin-top: 10px; }
        body.multi-copy .qr-code { width: 65px; height: 65px; }
        body.multi-copy .qr-text { font-size: 9px; }
        body.multi-copy .signature-line { width: 150px; padding-top: 3px; }
        body.multi-copy .copy-label { font-size: 9px; padding: 1px 6px; }
    </style>
</head>
@php $multiCopy = count($copyLabels ?? ['Original']) > 1; @endphp
<body class="{{ $multiCopy ? 'multi-copy' : '' }}">
@foreach (($copyLabels ?? ['Original']) as $copyIndex => $copyLabel)
@if(!$loop->first)
    <div class="cut-line">- - - - - - - - - - - - - - - cut here - - - - - - - - - - - - - - -</div>
@endif
<div class="copy-page">
    <div class="copy-label-bar"><span class="copy-label">{{ $copyLabel }}</span></div>

    @include('pdf._receipt-header')

    <div class="receipt-title">FEE RECEIPT</div>

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
            <td>{{ $payment->academicYear->name }}</td>
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
