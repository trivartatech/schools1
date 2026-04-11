<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\LeaveType;
use App\Models\Student;
use App\Models\StudentLeave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeaveController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve which student's data to serve.
     * For parents with multiple children, honour the X-Active-Student-Id header
     * or `student_id` query param. Always validates ownership.
     */
    private function resolveStudentId($user, ?Request $request = null): ?int
    {
        if ($user->isStudent()) {
            return $user->student?->id;
        }

        if ($user->isParent()) {
            $parent   = $user->studentParent;
            if (!$parent) return null;

            $children = $parent->students()->pluck('id');
            if ($children->isEmpty()) return null;

            // Check for explicit child selection
            $requested = $request?->header('X-Active-Student-Id')
                      ?? $request?->input('student_id');

            if ($requested && $children->contains((int)$requested)) {
                return (int)$requested;
            }

            // Default: first child
            return $children->first();
        }

        return null;
    }

    // ── Student Leave Management ────────────────────────────────────────────────

    public function leaveTypes(Request $request): JsonResponse
    {
        $school    = app('current_school');
        $studentId = $this->resolveStudentId($request->user(), $request);

        $types = LeaveType::where('school_id', $school->id)
            ->forStudents()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'color', 'days_allowed', 'requires_document', 'min_notice_days']);

        // Calculate used days per type for this student in the current academic year
        $balance = [];
        if ($studentId) {
            $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
            $usedQuery = StudentLeave::where('school_id', $school->id)
                ->where('student_id', $studentId)
                ->where('status', '!=', 'rejected');

            // Scope to current academic year if available
            if ($yearId) {
                $academicYear = AcademicYear::find($yearId);
                if ($academicYear) {
                    $usedQuery->where('start_date', '>=', $academicYear->start_date)
                              ->where('start_date', '<=', $academicYear->end_date);
                }
            }

            $usedLeaves = (clone $usedQuery)
                ->selectRaw("leave_type_id, SUM(CAST(julianday(end_date) - julianday(start_date) AS INTEGER) + 1) as days_used")
                ->groupBy('leave_type_id')
                ->pluck('days_used', 'leave_type_id');

            foreach ($types as $type) {
                $used = (int) ($usedLeaves[$type->id] ?? 0);
                $balance[$type->id] = [
                    'allowed'   => $type->days_allowed,
                    'used'      => $used,
                    'remaining' => max(0, $type->days_allowed - $used),
                ];
            }
        }

        return response()->json([
            'leave_types' => $types,
            'balance'     => $balance,
        ]);
    }

    public function leaves(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $studentId = $this->resolveStudentId($user, $request);

        $query = StudentLeave::where('school_id', $school->id)
            ->with(['leaveType:id,name,code,color', 'approver:id,name', 'appliedBy:id,name'])
            ->orderByDesc('created_at');

        // Scope based on role
        if ($user->isStudent()) {
            if (!$studentId) return response()->json(['leaves' => [], 'summary' => []]);
            $query->where('student_id', $studentId);
        } elseif ($user->isParent()) {
            $parent = $user->studentParent;
            if (!$parent) return response()->json(['leaves' => [], 'summary' => []]);
            $childIds = $parent->students()->pluck('id');
            if ($childIds->isEmpty()) return response()->json(['leaves' => [], 'summary' => []]);
            $query->whereIn('student_id', $childIds);
        }
        // Admin/teacher see all leaves for the school

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        $leaves = $query->paginate(20);

        // Summary counts
        $summaryQuery = StudentLeave::where('school_id', $school->id);
        if ($user->isStudent() && $studentId) {
            $summaryQuery->where('student_id', $studentId);
        } elseif ($user->isParent()) {
            $parent = $user->studentParent;
            if ($parent) {
                $childIds = $parent->students()->pluck('id');
                $summaryQuery->whereIn('student_id', $childIds);
            }
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

        return response()->json([
            'leaves'  => $leaves->items(),
            'summary' => $summary,
            'total'   => $leaves->total(),
            'page'    => $leaves->currentPage(),
        ]);
    }

    public function applyLeave(Request $request): JsonResponse
    {
        $user    = $request->user();
        $school  = app('current_school');

        // Students can only apply from today onward; management can backdate
        $startDateRule = ($user->isStudent() || $user->isParent())
            ? 'required|date|after_or_equal:today'
            : 'required|date';

        $validated = $request->validate([
            'student_id'    => 'required|exists:students,id',
            'leave_type_id' => 'nullable|exists:leave_types,id',
            'start_date'    => $startDateRule,
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'required|string|max:1000',
            'document'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Verify student belongs to this school
        $student = Student::where('id', $validated['student_id'])
            ->where('school_id', $school->id)
            ->firstOrFail();

        // Students can only apply for themselves
        if ($user->isStudent()) {
            $ownStudent = Student::where('school_id', $school->id)
                ->where('user_id', $user->id)->firstOrFail();
            if ($ownStudent->id !== $student->id) {
                return response()->json(['message' => 'You can only apply for your own leave.'], 403);
            }
        }

        // Parents can only apply for their own children
        if ($user->isParent()) {
            $isChild = Student::where('school_id', $school->id)
                ->where('id', $student->id)
                ->whereHas('studentParent', fn($q) => $q->where('user_id', $user->id))
                ->exists();
            if (!$isChild) {
                return response()->json(['message' => 'You can only apply for your own child\'s leave.'], 403);
            }
        }

        // Validate leave type
        if (!empty($validated['leave_type_id'])) {
            $leaveType = LeaveType::where('id', $validated['leave_type_id'])
                ->where('school_id', $school->id)
                ->forStudents()
                ->where('is_active', true)
                ->firstOrFail();

            if ($leaveType->min_notice_days > 0) {
                $minDate = now()->addDays($leaveType->min_notice_days)->toDateString();
                if ($validated['start_date'] < $minDate) {
                    return response()->json([
                        'message' => "This leave type requires at least {$leaveType->min_notice_days} day(s) advance notice.",
                        'errors'  => ['start_date' => ["Minimum {$leaveType->min_notice_days} day(s) notice required."]],
                    ], 422);
                }
            }
        }

        // Handle document upload
        $documentPath = $documentOriginalName = $documentMime = null;
        if ($request->hasFile('document') && $request->file('document')->isValid()) {
            $file     = $request->file('document');
            $safeName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $folder   = "student-leaves/{$school->id}/{$student->id}";
            $documentPath         = $file->storeAs($folder, $safeName, 'local');
            $documentOriginalName = $file->getClientOriginalName();
            $documentMime         = $file->getMimeType();
        }

        $leave = StudentLeave::create([
            'school_id'              => $school->id,
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

        $leave->load(['leaveType:id,name,code,color', 'appliedBy:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Leave application submitted successfully.',
            'leave'   => $leave,
        ], 201);
    }

    public function cancelLeave(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $leave = StudentLeave::where('school_id', $school->id)
            ->where('id', $id)
            ->firstOrFail();

        // Only the applicant can cancel, and only if still pending
        if ($leave->applied_by !== $user->id) {
            return response()->json(['message' => 'You can only cancel leaves you applied for.'], 403);
        }
        if ($leave->status !== 'pending') {
            return response()->json(['message' => 'Only pending leaves can be cancelled.'], 422);
        }

        $leave->delete();

        return response()->json(['success' => true, 'message' => 'Leave application cancelled.']);
    }
}
