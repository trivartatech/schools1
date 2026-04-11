<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\TransferCertificate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransferCertificateController extends Controller
{
    // ── Index ─────────────────────────────────────────────────

    public function index(Request $request)
    {
        $schoolId = app('current_school_id');

        $query = TransferCertificate::with([
                'student:id,first_name,last_name,admission_no,photo',
                'student.currentAcademicHistory.courseClass:id,name',
                'student.currentAcademicHistory.section:id,name',
                'requestedBy:id,name',
                'approvedBy:id,name',
            ])
            ->where('school_id', $schoolId);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('first_name', 'like', $search)
                  ->orWhere('last_name',  'like', $search)
                  ->orWhere('admission_no', 'like', $search);
            });
        }
        if ($request->filled('class_id')) {
            $classId = $request->class_id;
            $query->whereHas('student.currentAcademicHistory', fn($q) => $q->where('course_class_id', $classId));
        }

        $tcs = $query->latest()->paginate(20)->withQueryString();

        // Summary counts
        $counts = TransferCertificate::where('school_id', $schoolId)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $classes = \App\Models\CourseClass::where('school_id', $schoolId)
            ->orderBy('numeric_value')->get(['id', 'name']);

        return Inertia::render('School/Students/TransferCertificates/Index', [
            'tcs'     => $tcs,
            'counts'  => $counts,
            'classes' => $classes,
            'filters' => $request->only(['status', 'search', 'class_id']),
        ]);
    }

    // ── Create ────────────────────────────────────────────────

    public function create(Request $request)
    {
        $schoolId = app('current_school_id');

        // Student list — only active students who don't already have a pending/approved TC
        $existingStudentIds = TransferCertificate::where('school_id', $schoolId)
            ->whereIn('status', ['requested', 'approved'])
            ->pluck('student_id');

        $students = Student::with(['currentAcademicHistory.courseClass:id,name', 'currentAcademicHistory.section:id,name'])
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereNotIn('id', $existingStudentIds)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'admission_no']);

        // Pre-select a student if coming from the student profile
        $preselectedId = $request->query('student_id');

        return Inertia::render('School/Students/TransferCertificates/Create', [
            'students'      => $students,
            'preselectedId' => $preselectedId ? (int) $preselectedId : null,
        ]);
    }

    // ── Store ─────────────────────────────────────────────────

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'student_id'         => ['required', 'exists:students,id'],
            'leaving_date'       => ['required', 'date'],
            'reason'             => ['nullable', 'string', 'max:1000'],
            'conduct'            => ['required', 'in:Good,Satisfactory,Poor'],
            'last_class_studied' => ['nullable', 'string', 'max:100'],
            'fee_paid_upto'      => ['nullable', 'date'],
            'has_dues'           => ['boolean'],
        ]);

        // Ensure student belongs to this school
        $student = Student::where('school_id', $schoolId)->findOrFail($validated['student_id']);

        // Prevent duplicate active TC
        $existing = TransferCertificate::where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->whereIn('status', ['requested', 'approved'])
            ->first();

        if ($existing) {
            return back()->withErrors(['student_id' => 'This student already has a pending or approved TC request.']);
        }

        // Auto-fill last class studied if not provided
        if (empty($validated['last_class_studied'])) {
            $h = $student->currentAcademicHistory()->with('courseClass', 'section')->first();
            if ($h) {
                $validated['last_class_studied'] = $h->courseClass?->name . ($h->section ? ' - ' . $h->section->name : '');
            }
        }

        TransferCertificate::create([
            ...$validated,
            'school_id'    => $schoolId,
            'status'       => 'requested',
            'requested_by' => auth()->id(),
        ]);

        return redirect()->route('school.transfer-certificates.index')
            ->with('success', 'Transfer Certificate request submitted successfully.');
    }

    // ── Show ──────────────────────────────────────────────────

    public function show(TransferCertificate $transferCertificate)
    {
        abort_if($transferCertificate->school_id !== app('current_school_id'), 403);

        $transferCertificate->load([
            'student.currentAcademicHistory.courseClass',
            'student.currentAcademicHistory.section',
            'student.parent',
            'requestedBy:id,name',
            'approvedBy:id,name',
            'school:id,name,address,phone,email,logo',
        ]);

        return Inertia::render('School/Students/TransferCertificates/Show', [
            'tc' => $transferCertificate,
        ]);
    }

    // ── Approve ───────────────────────────────────────────────

    public function approve(Request $request, TransferCertificate $transferCertificate)
    {
        abort_if($transferCertificate->school_id !== app('current_school_id'), 403);
        abort_unless($transferCertificate->isRequested(), 422);

        $request->validate([
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $transferCertificate->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'remarks'     => $request->remarks,
        ]);

        return back()->with('success', 'TC request approved.');
    }

    // ── Reject ────────────────────────────────────────────────

    public function reject(Request $request, TransferCertificate $transferCertificate)
    {
        abort_if($transferCertificate->school_id !== app('current_school_id'), 403);
        abort_unless(in_array($transferCertificate->status, ['requested', 'approved']), 422);

        $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $transferCertificate->update([
            'status'  => 'rejected',
            'remarks' => $request->remarks,
        ]);

        return back()->with('success', 'TC request rejected.');
    }

    // ── Issue ─────────────────────────────────────────────────

    public function issue(Request $request, TransferCertificate $transferCertificate)
    {
        abort_if($transferCertificate->school_id !== app('current_school_id'), 403);
        abort_unless($transferCertificate->isApproved(), 422);

        $request->validate([
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        // Generate certificate number using school settings
        $settings  = app('current_school')->settings ?? [];
        $prefix    = $this->resolveTokens($settings['tc_prefix']    ?? 'TC/',   now());
        $suffix    = $this->resolveTokens($settings['tc_suffix']    ?? '/{YEAR}', now());
        $startNo   = (int) ($settings['tc_start_no']   ?? 1);
        $padLength = (int) ($settings['tc_pad_length'] ?? 4);

        $issuedCount = TransferCertificate::where('school_id', $transferCertificate->school_id)
            ->where('status', 'issued')
            ->count();

        $number = str_pad($startNo + $issuedCount, $padLength, '0', STR_PAD_LEFT);
        $certNo = $prefix . $number . $suffix;

        $transferCertificate->update([
            'status'         => 'issued',
            'certificate_no' => $certNo,
            'issued_at'      => now(),
            'remarks'        => $request->remarks ?? $transferCertificate->remarks,
        ]);

        // Mark student as TC in the students table
        $transferCertificate->student->update(['status' => 'tc']);

        return back()->with('success', "TC issued successfully. Certificate No: {$certNo}");
    }

    // ── Token Resolver ────────────────────────────────────────

    private function resolveTokens(string $template, \Carbon\Carbon $now): string
    {
        $ay = optional(
            \App\Models\AcademicYear::where('school_id', app('current_school_id'))
                ->where('is_current', true)->first()
        )->name ?? date('Y');

        return str_replace(
            ['{YEAR}', '{YY}',                    '{MONTH}',                    '{MM}',                        '{MON}',                             '{DD}',                   '{AY}'],
            [$now->year, $now->format('y'),        $now->format('m'),            $now->format('m'),             strtoupper($now->format('M')),        $now->format('d'),        $ay],
            $template
        );
    }

    // ── Print (printable certificate view) ───────────────────

    public function print(TransferCertificate $transferCertificate)
    {
        abort_if($transferCertificate->school_id !== app('current_school_id'), 403);
        abort_unless($transferCertificate->isIssued(), 403);

        $transferCertificate->load([
            'student',
            'student.currentAcademicHistory.courseClass',
            'student.currentAcademicHistory.section',
            'school:id,name,address,phone,email,logo',
        ]);

        return Inertia::render('School/Students/TransferCertificates/Print', [
            'tc' => $transferCertificate,
        ]);
    }
}
