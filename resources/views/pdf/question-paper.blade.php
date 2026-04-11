<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $paper->title }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #222;
            margin: 0;
            padding: 15px 25px;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 12px;
            margin-bottom: 10px;
        }
        .school-name {
            font-size: 22px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }
        .school-address {
            font-size: 10px;
            color: #666;
            margin-top: 3px;
        }
        .paper-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin: 12px 0 4px;
            text-decoration: underline;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 3px 8px;
            font-size: 12px;
        }
        .meta-label {
            font-weight: bold;
            color: #444;
        }
        .instructions-box {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            padding: 8px 12px;
            margin-bottom: 15px;
            font-size: 11px;
        }
        .instructions-box strong {
            display: block;
            margin-bottom: 4px;
        }
        .section-header {
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 8px;
        }
        .section-instruction {
            font-style: italic;
            color: #555;
            font-size: 11px;
            margin-bottom: 8px;
            padding-left: 12px;
        }
        .question {
            margin-bottom: 10px;
            padding-left: 5px;
        }
        .question-text {
            margin-bottom: 4px;
        }
        .question-num {
            font-weight: bold;
            color: #333;
        }
        .marks-badge {
            float: right;
            font-size: 10px;
            color: #666;
        }
        .options-grid {
            display: table;
            width: 100%;
            margin-top: 4px;
            margin-left: 20px;
        }
        .options-row {
            display: table-row;
        }
        .option-cell {
            display: table-cell;
            width: 50%;
            padding: 2px 5px;
            font-size: 11px;
        }
        .option-label {
            font-weight: bold;
        }
        .answer-section {
            page-break-before: always;
        }
        .answer-section .header {
            border-bottom: 1px solid #999;
        }
        .answer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .answer-table th, .answer-table td {
            border: 1px solid #ccc;
            padding: 6px 10px;
            text-align: left;
            font-size: 11px;
        }
        .answer-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .separator {
            border-bottom: 1px dashed #ccc;
            margin: 8px 0;
        }
    </style>
</head>
<body>
    {{-- ── School Header ── --}}
    <div class="header">
        <p class="school-name">{{ $school->name }}</p>
        @if($school->address)
            <p class="school-address">{{ $school->address }}</p>
        @endif
    </div>

    {{-- ── Paper Title & Meta ── --}}
    <div class="paper-title">{{ $paper->title }}</div>

    <table class="meta-table">
        <tr>
            <td><span class="meta-label">Class:</span> {{ $paper->courseClass->name }}</td>
            <td><span class="meta-label">Subject:</span> {{ $paper->subject->name }}</td>
            <td style="text-align:right"><span class="meta-label">Max Marks:</span> {{ $paper->total_marks }}</td>
        </tr>
        <tr>
            @if($paper->exam_type)
                <td><span class="meta-label">Exam:</span> {{ $paper->exam_type }}</td>
            @else
                <td></td>
            @endif
            <td><span class="meta-label">Duration:</span> {{ $paper->duration_minutes }} minutes</td>
            <td style="text-align:right"><span class="meta-label">Date:</span> _______________</td>
        </tr>
    </table>

    {{-- ── General Instructions ── --}}
    @if($paper->instructions)
        <div class="instructions-box">
            <strong>General Instructions:</strong>
            {!! nl2br(e($paper->instructions)) !!}
        </div>
    @endif

    {{-- ── Sections & Questions ── --}}
    @php $qNum = 1; @endphp
    @foreach($paper->sections as $section)
        <div class="section-header">
            {{ $section->name }}
            <span style="font-weight:normal; font-size:11px; color:#555;">
                ({{ $section->num_questions }} questions &times; {{ $section->marks_per_question }} marks = {{ $section->num_questions * $section->marks_per_question }} marks)
            </span>
        </div>

        @if($section->instructions)
            <div class="section-instruction">{{ $section->instructions }}</div>
        @endif

        @foreach($section->items as $item)
            <div class="question">
                <div class="question-text">
                    <span class="marks-badge">[{{ $item->marks }} Mark{{ $item->marks > 1 ? 's' : '' }}]</span>
                    <span class="question-num">{{ $qNum }}.</span>
                    {{ $item->question_text }}
                </div>

                @if($section->question_type === 'mcq' && $item->option_a)
                    <div class="options-grid">
                        <div class="options-row">
                            <div class="option-cell"><span class="option-label">A)</span> {{ $item->option_a }}</div>
                            <div class="option-cell"><span class="option-label">B)</span> {{ $item->option_b }}</div>
                        </div>
                        <div class="options-row">
                            <div class="option-cell"><span class="option-label">C)</span> {{ $item->option_c }}</div>
                            <div class="option-cell"><span class="option-label">D)</span> {{ $item->option_d }}</div>
                        </div>
                    </div>
                @endif
            </div>
            @php $qNum++; @endphp
        @endforeach
    @endforeach

    {{-- ── Answer Key (separate page) ── --}}
    @if(request()->query('with_answers'))
        <div class="answer-section">
            <div class="header">
                <p class="school-name" style="font-size:16px;">Answer Key</p>
                <p class="school-address">{{ $paper->title }}</p>
            </div>

            @php $aNum = 1; @endphp
            @foreach($paper->sections as $section)
                <div class="section-header" style="margin-top:10px;">{{ $section->name }}</div>
                <table class="answer-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">Q.No.</th>
                            <th>Answer / Model Response</th>
                            <th style="width:60px;">Marks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section->items as $item)
                            <tr>
                                <td>{{ $aNum }}</td>
                                <td>{{ $item->correct_answer ?? '—' }}</td>
                                <td>{{ $item->marks }}</td>
                            </tr>
                            @php $aNum++; @endphp
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        </div>
    @endif
</body>
</html>
