@php
    $student = $payment->student;
    $parent  = $student?->studentParent ?? null;

    // Class & Section: from academic history matching this payment's academic year
    $academicHistory = null;
    if ($student && $student->relationLoaded('academicHistories')) {
        $academicHistory = $student->academicHistories->firstWhere('academic_year_id', $payment->academic_year_id);
    }
    $className   = $academicHistory ? optional($academicHistory->courseClass)->name : null;
    $sectionName = $academicHistory ? optional($academicHistory->section)->name : null;
    $classSection = trim(($className ?? '') . (($className && $sectionName) ? ' - ' : '') . ($sectionName ?? ''));

    // Father's name
    $fatherName = $parent?->father_name;

    // Mobile: prefer father's, then primary, then mother's, then student's emergency contact
    $mobile = $parent?->father_phone
        ?: $parent?->primary_phone
        ?: $parent?->mother_phone
        ?: ($student?->emergency_contact_phone);

    // Address: prefer student's own (line + city + state + pin), fall back to parent
    $studentAddressParts = array_filter([
        $student?->address,
        $student?->city,
        $student?->state,
        $student?->pincode,
    ]);
    $studentAddress = implode(', ', $studentAddressParts);
    if ($studentAddress === '' && !empty($parent?->address)) {
        $studentAddress = $parent->address;
    }

    // Payment mode (handles both enum and plain string)
    $paymentMode = strtoupper(
        $payment->payment_mode instanceof \App\Enums\PaymentMode
            ? $payment->payment_mode->value
            : (string) $payment->payment_mode
    );
@endphp

<table class="info-table">
    <tr>
        <td class="label">Receipt No:</td>
        <td><strong>{{ $payment->receipt_no }}</strong></td>
        <td class="label">Payment Date:</td>
        <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d-M-Y') }}</td>
    </tr>
    <tr>
        <td class="label">Student Name:</td>
        <td><strong>{{ trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')) ?: '—' }}</strong></td>
        <td class="label">Admission No:</td>
        <td>{{ $student?->admission_no ?: '—' }}</td>
    </tr>
    <tr>
        <td class="label">Class / Section:</td>
        <td>{{ $classSection !== '' ? $classSection : '—' }}</td>
        <td class="label">Academic Year:</td>
        <td>{{ optional($payment->academicYear)->name ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Father's Name:</td>
        <td>{{ $fatherName ?: '—' }}</td>
        <td class="label">Mobile:</td>
        <td>{{ $mobile ?: '—' }}</td>
    </tr>
    @if($studentAddress !== '')
    <tr>
        <td class="label">Address:</td>
        <td colspan="3">{{ $studentAddress }}</td>
    </tr>
    @endif
    <tr>
        <td class="label">Payment Mode:</td>
        <td>{{ $paymentMode ?: '—' }}</td>
        @if(!empty($payment->transaction_ref))
            <td class="label">Transaction Ref:</td>
            <td>{{ $payment->transaction_ref }}</td>
        @else
            <td></td>
            <td></td>
        @endif
    </tr>
</table>
