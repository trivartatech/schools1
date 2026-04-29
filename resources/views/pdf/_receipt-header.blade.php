@php
    // Logo: read from public storage and embed as base64 (most reliable in DomPDF)
    $logoData = null;
    if (!empty($school->logo)) {
        $logoFsPath = storage_path('app/public/' . ltrim($school->logo, '/'));
        if (is_file($logoFsPath)) {
            $mime = function_exists('mime_content_type') ? (mime_content_type($logoFsPath) ?: 'image/png') : 'image/png';
            $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoFsPath));
        }
    }

    // Address: prefer Settings JSON (newer General Config), fall back to legacy column
    $line1 = $school->settings['address_line1'] ?? $school->address ?? null;
    $line2 = $school->settings['address_line2'] ?? null;
    $cityStatePin = trim(implode(', ', array_filter([
        $school->city ?? null,
        $school->state ?? null,
        $school->pincode ?? null,
    ])), ', ');
@endphp

<div class="header">
    @if($logoData)
        <img src="{{ $logoData }}" alt="" class="school-logo">
    @endif

    <h1 class="school-name">{{ $school->name }}</h1>

    @if($line1 || $line2 || $cityStatePin)
        <div class="school-address">
            @if($line1) {{ $line1 }} <br> @endif
            @if($line2) {{ $line2 }} <br> @endif
            @if($cityStatePin) {{ $cityStatePin }} @endif
        </div>
    @endif

    @if(!empty($school->phone) || !empty($school->email))
        <div class="school-address">
            @if(!empty($school->phone)) Phone: {{ $school->phone }} @endif
            @if(!empty($school->phone) && !empty($school->email)) | @endif
            @if(!empty($school->email)) Email: {{ $school->email }} @endif
        </div>
    @endif
</div>
