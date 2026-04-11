<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\CourseClass;
use App\Models\LeaveType;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\StudentLeave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StudentLeaveController extends Controller
{
    /**
     * Admin / Teacher: list all student leave applications with filters.
     * Student: only sees their own leave history.
     * Parent: only sees their child(ren)'s leave history.
     */
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $user     = Auth::user();

        $query = StudentLeave::where('school_id', $schoolId)
            ->with(['student', 'leaveType', 'approver', 'appliedBy'])
            ->latest();

        // Resolve the authenticated user's own student record once (reused for summary below).
        $ownStudentId  = null;
        $ownStudentIds = null; // for parent

        // Scope for student self-service
        if ($user->user_type === 'student') {
            $ownStudent = Student::where('school_id', $schoolId)
                ->where('user_id', $user->id)
                ->firstOrFail();
            $ownStudentId = $ownStudent->id;
            $query->where('student_id', $ownStudentId);
        }

        // Scope for parent — shows all their children's leaves
        if ($user->user_type === 'parent') {
            $ownStudentIds = Student::where('school_id', $schoolId)
                ->whereHas('studentParent', fn($q) => $q->where('user_id', $user->id))
                ->pluck('id');
            $query->whereIn('student_id', $ownStudentIds);
        }

        // Filters (admin/teacher only make sense here)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        if ($request->filled('from')) {
            $query->where('start_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('end_date', '<=', $request->to);
        }

        $leaves = $query->paginate(20)->withQueryString();

        // Leave types scoped for student use
        $leaveTypes = LeaveType::where('school_id', $schoolId)
            ->forStudents()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'color', 'days_allowed', 'requires_document', 'min_notice_days']);

        // Classes + sections for the cascading filter in the apply form
        $classes  = [];
        $sections = [];
        $students = [];

        if (in_array($user->user_type, ['admin', 'super_admin', 'teacher', 'school_admin', 'principal'])) {
            $classes = CourseClass::where('school_id', $schoolId)
                ->orderBy('numeric_value')
                ->orderBy('name')
                ->get(['id', 'name']);

            $sections = Section::where('school_id', $schoolId)
                ->orderBy('name')
                ->get(['id', 'course_class_id', 'name']);

            // Students with their current class_id and section_id from academic history
            $academicYearId = app()->bound('current_academic_year_id')
                ? app('current_academic_year_id')
                : null;

            $historyQuery = StudentAcademicHistory::where('school_id', $schoolId)
                ->with('student:id,first_name,last_name,admission_no,status')
                ->select('student_id', 'class_id', 'section_id');

            if ($academicYearId) {
                $historyQuery->where('academic_year_id', $academicYearId);
            }

            $students = $historyQuery->get()
                ->filter(fn($h) => $h->student && $h->student->status === 'active')
                ->map(fn($h) => [
                    'id'           => $h->student->id,
                    'first_name'   => $h->student->first_name,
                    'last_name'    => $h->student->last_name,
                    'admission_no' => $h->student->admission_no,
                    'class_id'     => $h->class_id,
                    'section_id'   => $h->section_id,
                ])
                ->values();
        }

        // Summary counts — single GROUP BY query instead of 4 separate COUNTs
        $summaryQuery = StudentLeave::where('school_id', $schoolId);
        if ($user->user_type === 'student' && $ownStudentId) {
            $summaryQuery->where('student_id', $ownStudentId);
        }
        if ($user->user_type === 'parent' && $ownStudentIds) {
            $summaryQuery->whereIn('student_id', $ownStudentIds);
        }

        $statusCounts = (clone $summaryQuery)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $summary = [
            'total'    => $statusCounts->sum(),
            'pending'  => $statusCounts['pending']  ?? 0,
            'approved' => $statusCounts['approved'] ?? 0,
            'rejected' => $statusCounts['rejected'] ?? 0,
        ];

        // Build own_children list for parent role (so they can pick which child)
        $ownChildren = [];
        if ($user->user_type === 'parent' && $ownStudentIds) {
            $ownChildren = Student::whereIn('id', $ownStudentIds)
                ->get(['id', 'first_name', 'last_name', 'admission_no'])
                ->toArray();
        }

        return Inertia::render('School/Students/Leaves/Index', [
            'leaves'         => $leaves,
            'leaveTypes'     => $leaveTypes,
            'classes'        => $classes,
            'sections'       => $sections,
            'students'       => $students,
            'summary'        => $summary,
            'filters'        => $request->only(['status', 'student_id', 'leave_type_id', 'from', 'to']),
            'own_student_id' => $ownStudentId,   // pre-fills student_id for student role
            'own_children'   => $ownChildren,    // child list for parent role selector
        ]);
    }

    /**
     * Apply for leave.
     * - Admin/teacher can apply on behalf of any student (requires student_id in payload).
     * - Student can apply for themselves.
     * - Parent can apply for their child (requires student_id in payload).
     */
    public function store(Request $request)
    {
        $schoolId = app('current_school_id');
        $user     = Auth::user();

        // Students can only apply from today onward; management can backdate for absent students.
        $startDateRule = in_array($user->user_type, ['student', 'parent'])
            ? 'required|date|after_or_equal:today'
            : 'required|date';

        $validated = $request->validate([
            'student_id'    => 'required|exists:students,id',
            'leave_type_id' => 'nullable|exists:leave_types,id',
            'start_date'    => $startDateRule,
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'required|string|max:1000',
            'document'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5 MB max
        ]);

        // Verify student belongs to this school
        $student = Student::where('id', $validated['student_id'])
            ->where('school_id', $schoolId)
            ->firstOrFail();

        // Students can only apply for themselves
        if ($user->user_type === 'student') {
            $ownStudent = Student::where('school_id', $schoolId)
                ->where('user_id', $user->id)
                ->firstOrFail();
            abort_if($ownStudent->id !== $student->id, 403, 'You can only apply for your own leave.');
        }

        // Parents can only apply for their own children
        if ($user->user_type === 'parent') {
            $isChild = Student::where('school_id', $schoolId)
                ->where('id', $student->id)
                ->whereHas('studentParent', fn($q) => $q->where('user_id', $user->id))
                ->exists();
            abort_if(!$isChild, 403, 'You can only apply for your own child\'s leave.');
        }

        // Validate leave_type belongs to this school and is for students
        if (!empty($validated['leave_type_id'])) {
            $leaveType = LeaveType::where('id', $validated['leave_type_id'])
                ->where('school_id', $schoolId)
                ->whereIn('applicable_to', ['student', 'both'])
                ->where('is_active', true)
                ->firstOrFail();

            // Minimum notice check
            if ($leaveType->min_notice_days > 0) {
                $minDate = now()->addDays($leaveType->min_notice_days)->toDateString();
                if ($validated['start_date'] < $minDate) {
                    return back()->withErrors([
                        'start_date' => "This leave type requires at least {$leaveType->min_notice_days} day(s) advance notice.",
                    ]);
                }
            }
        }

        // Handle document upload
        $documentPath         = null;
        $documentOriginalName = null;
        $documentMime         = null;

        if ($request->hasFile('document') && $request->file('document')->isValid()) {
            $file     = $request->file('document');
            $ext      = $file->getClientOriginalExtension();
            $safeName = Str::uuid() . '.' . $ext;
            $folder   = "student-leaves/{$schoolId}/{$student->id}";

            // Store privately (not publicly accessible — served through controller)
            $documentPath         = $file->storeAs($folder, $safeName, 'local');
            $documentOriginalName = $file->getClientOriginalName();
            $documentMime         = $file->getMimeType();
        }

        StudentLeave::create([
            'school_id'              => $schoolId,
            'student_id'             => $student->id,
            'leave_type_id'          => $validated['leave_type_id'] ?? null,
            'start_date'             => $validated['start_date'],
            'end_date'               => $validated['end_date'],
            'reason'                 => $validated['reason'],
            'status'                 => 'pending',
            'applied_by'             => $user->id,
            'document_path'          => $documentPath,
            'document_original_name' => $documentOriginalName,
            'document_mime'          => $documentMime,
        ]);

        return back()->with('success', 'Leave application submitted successfully.');
    }

    /**
     * Admin / Teacher: approve a student leave.
     */
    public function approve(Request $request, StudentLeave $studentLeave)
    {
        $this->authorizeLeave($studentLeave);

        $validated = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $studentLeave->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'remarks'     => $validated['remarks'] ?? null,
        ]);

        return back()->with('success', 'Leave approved successfully.');
    }

    /**
     * Admin / Teacher: reject a student leave.
     */
    public function reject(Request $request, StudentLeave $studentLeave)
    {
        $this->authorizeLeave($studentLeave);

        $validated = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $studentLeave->update([
            'status'      => 'rejected',
            'approved_by' => Auth::id(),
            'remarks'     => $validated['remarks'] ?? null,
        ]);

        return back()->with('success', 'Leave rejected.');
    }

    /**
     * Admin: revert an approved/rejected leave back to pending.
     */
    public function revert(StudentLeave $studentLeave)
    {
        $this->authorizeLeave($studentLeave);

        $studentLeave->update([
            'status'      => 'pending',
            'approved_by' => null,
            'remarks'     => null,
        ]);

        return back()->with('success', 'Leave reverted to pending.');
    }

    /**
     * Securely serve the uploaded leave document.
     *
     * Gate: user must have the 'download_student_leave_document' Spatie permission.
     * Scope: students only see their own document; parents only see their child's.
     *        Management (admin/teacher) can see any document for the school.
     */
    public function document(StudentLeave $studentLeave)
    {
        $schoolId = app('current_school_id');
        $user     = Auth::user();

        // 1. Must belong to the current school tenant
        abort_if($studentLeave->school_id !== $schoolId, 403);

        // 2. Must have the Spatie permission
        abort_unless($user->can('download_student_leave_document'), 403, 'You do not have permission to download leave documents.');

        // 3. Must have a document attached
        abort_if(!$studentLeave->document_path, 404, 'No document attached to this leave.');

        // 4. Ownership scoping for student and parent roles
        if ($user->user_type === 'student') {
            $ownStudentId = Student::where('school_id', $schoolId)
                ->where('user_id', $user->id)
                ->value('id');
            abort_unless($ownStudentId === $studentLeave->student_id, 403, 'You can only view your own leave documents.');
        }

        if ($user->user_type === 'parent') {
            $isChild = Student::where('school_id', $schoolId)
                ->where('id', $studentLeave->student_id)
                ->whereHas('studentParent', fn($q) => $q->where('user_id', $user->id))
                ->exists();
            abort_unless($isChild, 403, 'You can only view documents for your own children.');
        }

        // 5. Physical file must exist
        abort_unless(
            Storage::disk('local')->exists($studentLeave->document_path),
            404,
            'Document file not found on storage.'
        );

        $fileName = $studentLeave->document_original_name ?? 'leave-document';

        return Storage::disk('local')->response(
            $studentLeave->document_path,
            $fileName,
            ['Content-Type' => $studentLeave->document_mime ?? 'application/octet-stream']
        );
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function authorizeLeave(StudentLeave $leave): void
    {
        abort_if($leave->school_id !== app('current_school_id'), 403);

        // Use Spatie permission check instead of user_type to support custom role assignments.
        abort_unless(
            Auth::user()->can('approve_student_leaves'),
            403,
            'You are not authorized to approve or reject leave applications.'
        );
    }
}
