<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryRecord;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mobile Disciplinary Records endpoints.
 *
 * Mirrors App\Http\Controllers\School\DisciplinaryController (admin-only on
 * web via school.management:admin_only) so a principal walking through the
 * corridor can record an incident or update a status without opening a
 * laptop. Same `disciplinary_records` table, same validation rules — web
 * and mobile surfaces stay in lock-step.
 */
class DisciplinaryController extends Controller
{
    private function assertAdmin(Request $request): void
    {
        $user = $request->user();
        $type = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        if (!in_array($type, ['admin', 'school_admin', 'principal', 'super_admin'], true)) {
            abort(response()->json(['error' => 'Unauthorized.'], 403));
        }
    }

    private function recordPayload(DisciplinaryRecord $r): array
    {
        $student = $r->student;
        $hist    = $student?->currentAcademicHistory;
        $cls     = $hist?->courseClass?->name;
        $sec     = $hist?->section?->name;

        return [
            'id'                   => $r->id,
            'incident_date'        => $r->incident_date instanceof \Carbon\Carbon
                ? $r->incident_date->toDateString()
                : (is_string($r->incident_date) ? substr($r->incident_date, 0, 10) : null),
            'category'             => $r->category,
            'severity'             => $r->severity,
            'status'               => $r->status,
            'description'          => $r->description,
            'action_taken'         => $r->action_taken,
            'consequence'          => $r->consequence,
            'consequence_from'     => $r->consequence_from instanceof \Carbon\Carbon
                ? $r->consequence_from->toDateString() : (is_string($r->consequence_from) ? substr($r->consequence_from, 0, 10) : null),
            'consequence_to'       => $r->consequence_to instanceof \Carbon\Carbon
                ? $r->consequence_to->toDateString()   : (is_string($r->consequence_to) ? substr($r->consequence_to, 0, 10) : null),
            'parent_notified'      => (bool) $r->parent_notified,
            'parent_notified_at'   => $r->parent_notified_at?->toIso8601String(),
            'student_statement'    => $r->student_statement,
            'notes'                => $r->notes,
            'student' => $student ? [
                'id'           => $student->id,
                'name'         => trim($student->first_name . ' ' . $student->last_name),
                'admission_no' => $student->admission_no,
                'class'        => trim(($cls ?? '') . ($sec ? " - {$sec}" : ''), ' -'),
            ] : null,
            'reported_by' => $r->reportedBy ? [
                'id'   => $r->reportedBy->id,
                'name' => $r->reportedBy->name,
            ] : null,
            'reviewed_by' => $r->reviewedBy ? [
                'id'   => $r->reviewedBy->id,
                'name' => $r->reviewedBy->name,
            ] : null,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    /**
     * GET /mobile/disciplinary
     *
     * Paginated list with filters. Returns summary stats over the full
     * filter set so the dashboard at the top of the screen stays correct
     * regardless of which page is currently being viewed.
     *
     * Query params:
     *   status       open | under_review | resolved | escalated
     *   severity     minor | moderate | major
     *   student_id   restrict to one student
     *   search       substring of description/category
     *   per_page     5..100 (default 30)
     *   page         >=1
     */
    public function index(Request $request): JsonResponse
    {
        $this->assertAdmin($request);
        $schoolId = app('current_school_id');

        $base = DisciplinaryRecord::where('school_id', $schoolId)
            ->with([
                'student:id,first_name,last_name,admission_no,parent_id',
                'student.currentAcademicHistory.courseClass:id,name',
                'student.currentAcademicHistory.section:id,name',
                'reportedBy:id,name',
                'reviewedBy:id,name',
            ]);

        if (in_array($request->input('status'), ['open', 'under_review', 'resolved', 'escalated'], true)) {
            $base->where('status', $request->input('status'));
        }
        if (in_array($request->input('severity'), ['minor', 'moderate', 'major'], true)) {
            $base->where('severity', $request->input('severity'));
        }
        if ($request->filled('student_id')) {
            $base->where('student_id', (int) $request->input('student_id'));
        }
        if ($search = trim((string) $request->input('search'))) {
            $base->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('category',  'like', "%{$search}%");
            });
        }

        // Stats over the same filter, NOT the current page slice.
        $stats = (clone $base)
            ->selectRaw('
                COUNT(*) as total_count,
                SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END)         as open_count,
                SUM(CASE WHEN status = "under_review" THEN 1 ELSE 0 END) as review_count,
                SUM(CASE WHEN status = "resolved" THEN 1 ELSE 0 END)     as resolved_count,
                SUM(CASE WHEN status = "escalated" THEN 1 ELSE 0 END)    as escalated_count,
                SUM(CASE WHEN severity = "major" THEN 1 ELSE 0 END)      as major_count
            ')
            ->first();

        $perPage   = max(5, min(100, (int) $request->input('per_page', 30)));
        $paginated = $base->orderByDesc('incident_date')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data'         => collect($paginated->items())->map(fn($r) => $this->recordPayload($r))->values(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'summary' => [
                'total'      => (int) ($stats->total_count    ?? 0),
                'open'       => (int) ($stats->open_count     ?? 0),
                'review'     => (int) ($stats->review_count   ?? 0),
                'resolved'   => (int) ($stats->resolved_count ?? 0),
                'escalated'  => (int) ($stats->escalated_count ?? 0),
                'major'      => (int) ($stats->major_count    ?? 0),
            ],
        ]);
    }

    /** POST /mobile/disciplinary — create a record */
    public function store(Request $request): JsonResponse
    {
        $this->assertAdmin($request);
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'student_id'        => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'incident_date'     => 'required|date',
            'category'          => 'required|string|max:100',
            'severity'          => 'required|in:minor,moderate,major',
            'description'       => 'required|string',
            'action_taken'      => 'nullable|string',
            'consequence'       => 'nullable|in:warning,detention,parent_call,suspension,expulsion,none',
            'consequence_from'  => 'nullable|date',
            'consequence_to'    => 'nullable|date|after_or_equal:consequence_from',
            'student_statement' => 'nullable|string',
            'notes'             => 'nullable|string',
        ]);

        $record = new DisciplinaryRecord($validated);
        $record->school_id   = $schoolId;
        $record->reported_by = $request->user()->id;
        $record->status      = 'open';
        $record->save();
        $record->load([
            'student:id,first_name,last_name,admission_no,parent_id',
            'student.currentAcademicHistory.courseClass:id,name',
            'student.currentAcademicHistory.section:id,name',
            'reportedBy:id,name',
        ]);

        return response()->json([
            'message' => 'Disciplinary record created.',
            'data'    => $this->recordPayload($record),
        ], 201);
    }

    /**
     * PATCH /mobile/disciplinary/{id}
     *
     * Update status / consequence / notes / parent_notified. Mirrors the
     * web update() — when the status moves to resolved/escalated, the
     * acting admin is recorded as reviewed_by; flipping parent_notified
     * from false to true stamps parent_notified_at.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->assertAdmin($request);
        $schoolId = app('current_school_id');

        $record = DisciplinaryRecord::where('school_id', $schoolId)->find($id);
        if (!$record) {
            return response()->json(['error' => 'Record not found.'], 404);
        }

        $validated = $request->validate([
            'incident_date'     => 'sometimes|required|date',
            'category'          => 'sometimes|required|string|max:100',
            'severity'          => 'sometimes|required|in:minor,moderate,major',
            'description'       => 'sometimes|required|string',
            'action_taken'      => 'nullable|string',
            'status'            => 'sometimes|required|in:open,under_review,resolved,escalated',
            'consequence'       => 'nullable|in:warning,detention,parent_call,suspension,expulsion,none',
            'consequence_from'  => 'nullable|date',
            'consequence_to'    => 'nullable|date|after_or_equal:consequence_from',
            'parent_notified'   => 'sometimes|boolean',
            'student_statement' => 'nullable|string',
            'notes'             => 'nullable|string',
        ]);

        // Stamp parent_notified_at only on the false → true transition.
        if (array_key_exists('parent_notified', $validated) && $validated['parent_notified'] && !$record->parent_notified) {
            $validated['parent_notified_at'] = now();
        }

        // When closing or escalating, record who did it.
        $newStatus = $validated['status'] ?? $record->status;
        if (in_array($newStatus, ['resolved', 'escalated'], true)
            && in_array($record->status, ['open', 'under_review'], true)) {
            $record->reviewed_by = $request->user()->id;
        }

        $record->fill($validated);
        $record->save();
        $record->load([
            'student:id,first_name,last_name,admission_no,parent_id',
            'student.currentAcademicHistory.courseClass:id,name',
            'student.currentAcademicHistory.section:id,name',
            'reportedBy:id,name',
            'reviewedBy:id,name',
        ]);

        return response()->json([
            'message' => 'Record updated.',
            'data'    => $this->recordPayload($record),
        ]);
    }

    /** GET /mobile/disciplinary/student/{studentId} — full history for one student */
    public function studentHistory(Request $request, int $studentId): JsonResponse
    {
        $this->assertAdmin($request);
        $schoolId = app('current_school_id');

        $student = Student::where('school_id', $schoolId)->find($studentId);
        if (!$student) {
            return response()->json(['error' => 'Student not found.'], 404);
        }

        $records = DisciplinaryRecord::where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->with([
                'student:id,first_name,last_name,admission_no',
                'reportedBy:id,name',
                'reviewedBy:id,name',
            ])
            ->orderByDesc('incident_date')->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'student' => [
                    'id'           => $student->id,
                    'name'         => trim($student->first_name . ' ' . $student->last_name),
                    'admission_no' => $student->admission_no,
                ],
                'records' => $records->map(fn($r) => $this->recordPayload($r))->values(),
                'summary' => [
                    'total'  => $records->count(),
                    'open'   => $records->where('status', 'open')->count(),
                    'review' => $records->where('status', 'under_review')->count(),
                    'major'  => $records->where('severity', 'major')->count(),
                ],
            ],
        ]);
    }
}
