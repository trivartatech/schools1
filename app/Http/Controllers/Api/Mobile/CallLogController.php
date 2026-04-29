<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mobile Call-Log endpoints.
 *
 * Mirrors App\Http\Controllers\School\FrontOffice\CallLogController. Same
 * `call_logs` table, same validation rules. Mobile differences:
 *   - List is paginated (the web returns the full set in one go).
 *   - Active academic year is read from app('current_academic_year_id')
 *     instead of session('selected_academic_year_id'); this is what the
 *     mobile tenant middleware sets so the value is always present.
 *   - Staff options are returned alongside the list response so the
 *     mobile form can populate the "Handled by" dropdown without a
 *     second round trip. Student linking is intentionally omitted on
 *     the mobile create form (kept on the read response when present).
 */
class CallLogController extends Controller
{
    private function assertFrontOfficeAccess(Request $request): void
    {
        $user = $request->user();
        // Parents and students should never log front-office calls.
        if ($user->isParent() || $user->isStudent()) {
            abort(response()->json(['error' => 'Unauthorized.'], 403));
        }
    }

    private function logPayload(CallLog $log): array
    {
        $today = now()->toDateString();
        $followUpDate = $log->follow_up_date instanceof \Carbon\Carbon
            ? $log->follow_up_date->toDateString()
            : (is_string($log->follow_up_date) ? substr($log->follow_up_date, 0, 10) : null);

        $followUpBucket = null;
        if ($followUpDate && !$log->follow_up_completed) {
            if ($followUpDate < $today)      $followUpBucket = 'overdue';
            elseif ($followUpDate === $today) $followUpBucket = 'today';
            else                              $followUpBucket = 'upcoming';
        } elseif ($log->follow_up_completed) {
            $followUpBucket = 'completed';
        }

        $student = $log->relatedStudent;
        $handler = $log->handledBy;

        return [
            'id'                 => $log->id,
            'caller_name'        => $log->caller_name,
            'phone_number'       => $log->phone_number,
            'call_type'          => $log->call_type,
            'purpose'            => $log->purpose,
            'notes'              => $log->notes,
            'follow_up_date'     => $followUpDate,
            'follow_up_completed'=> (bool) $log->follow_up_completed,
            'follow_up_bucket'   => $followUpBucket,
            'handled_by'         => $handler ? [
                'id'   => $handler->id,
                'name' => $handler->user?->name
                    ?? trim(($handler->first_name ?? '') . ' ' . ($handler->last_name ?? '')),
            ] : null,
            'related_student'    => $student ? [
                'id'           => $student->id,
                'name'         => trim($student->first_name . ' ' . $student->last_name),
                'admission_no' => $student->admission_no,
            ] : null,
            'created_at'         => $log->created_at?->toIso8601String(),
            'updated_at'         => $log->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Staff list shaped for the "Handled by" dropdown. Returns active staff
     * whose user account is also active.
     */
    private function staffOptions(int $schoolId): array
    {
        return Staff::where('staff.school_id', $schoolId)
            ->join('users', 'users.id', '=', 'staff.user_id')
            ->orderBy('users.name')
            ->select('staff.id', 'staff.user_id', 'users.name as user_name')
            ->get()
            ->map(fn ($s) => [
                'id'   => $s->id,
                'name' => $s->user_name ?: ('Staff #' . $s->id),
            ])
            ->values()
            ->all();
    }

    /**
     * GET /mobile/front-office/call-logs
     *
     * Query params (all optional):
     *   search        substring match against caller_name and phone_number
     *   call_type     'Incoming' | 'Outgoing'
     *   purpose       'Enquiry' | 'Complaint' | 'Follow-up' | 'Admission' | 'Other'
     *   has_follow_up '1' to return only logs with a non-null follow_up_date
     *   per_page      5..100 (default 30)
     *   page          >=1 (default 1)
     */
    public function index(Request $request): JsonResponse
    {
        $this->assertFrontOfficeAccess($request);
        $schoolId = app('current_school_id');
        $yearId   = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $query = CallLog::where('school_id', $schoolId)
            ->with(['handledBy.user', 'relatedStudent']);

        if ($yearId) {
            $query->where('academic_year_id', $yearId);
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('caller_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if (in_array($request->input('call_type'), ['Incoming', 'Outgoing'], true)) {
            $query->where('call_type', $request->input('call_type'));
        }

        if (in_array($request->input('purpose'), ['Enquiry', 'Complaint', 'Follow-up', 'Admission', 'Other'], true)) {
            $query->where('purpose', $request->input('purpose'));
        }

        if ($request->boolean('has_follow_up')) {
            $query->whereNotNull('follow_up_date');
        }

        $perPage = max(5, min(100, (int) $request->input('per_page', 30)));
        $paginated = $query->latest('id')->paginate($perPage);

        return response()->json([
            'data'         => collect($paginated->items())->map(fn ($l) => $this->logPayload($l))->values(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'meta'         => [
                'staff_options' => $this->staffOptions($schoolId),
            ],
        ]);
    }

    /**
     * POST /mobile/front-office/call-logs
     * Same validation rules as the web CallLogController::store().
     */
    public function store(Request $request): JsonResponse
    {
        $this->assertFrontOfficeAccess($request);
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'caller_name'        => 'required|string|max:255',
            'phone_number'       => 'required|string|max:25',
            'call_type'          => 'required|in:Incoming,Outgoing',
            'purpose'            => 'required|in:Enquiry,Complaint,Follow-up,Admission,Other',
            'handled_by_id'      => ['nullable', Rule::exists('staff', 'id')->where('school_id', $schoolId)],
            'related_student_id' => ['nullable', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'notes'              => 'nullable|string',
            'follow_up_date'     => 'nullable|date',
        ]);

        $log = new CallLog($validated);
        $log->school_id = $schoolId;
        $log->academic_year_id = app()->bound('current_academic_year_id')
            ? app('current_academic_year_id')
            : null;
        $log->save();
        $log->load(['handledBy.user', 'relatedStudent']);

        return response()->json([
            'message' => 'Call log recorded.',
            'data'    => $this->logPayload($log),
        ], 201);
    }

    /**
     * PATCH /mobile/front-office/call-logs/{id}/follow-up
     * Toggle the follow_up_completed flag.
     */
    public function updateFollowUp(Request $request, int $id): JsonResponse
    {
        $this->assertFrontOfficeAccess($request);
        $schoolId = app('current_school_id');

        $log = CallLog::where('school_id', $schoolId)->find($id);
        if (!$log) {
            return response()->json(['error' => 'Call log not found.'], 404);
        }

        $validated = $request->validate([
            'follow_up_completed' => 'required|boolean',
        ]);
        $log->update($validated);
        $log->load(['handledBy.user', 'relatedStudent']);

        return response()->json([
            'message' => 'Follow-up status updated.',
            'data'    => $this->logPayload($log),
        ]);
    }

    /** DELETE /mobile/front-office/call-logs/{id} — soft-delete a call log */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->assertFrontOfficeAccess($request);
        $schoolId = app('current_school_id');

        $log = CallLog::where('school_id', $schoolId)->find($id);
        if (!$log) {
            return response()->json(['error' => 'Call log not found.'], 404);
        }
        $log->delete();
        return response()->json(['message' => 'Call log removed.', 'id' => $id]);
    }

    /**
     * GET /mobile/front-office/call-logs/follow-ups
     * Dashboard buckets matching the web's CallLogController::followUps().
     */
    public function followUps(Request $request): JsonResponse
    {
        $this->assertFrontOfficeAccess($request);
        $schoolId = app('current_school_id');
        $today    = now()->toDateString();

        $base = CallLog::where('school_id', $schoolId)
            ->whereNotNull('follow_up_date')
            ->with(['handledBy.user', 'relatedStudent']);

        $overdue = (clone $base)->where('follow_up_completed', false)
            ->where('follow_up_date', '<', $today)
            ->orderBy('follow_up_date')->get();

        $todayList = (clone $base)->where('follow_up_completed', false)
            ->where('follow_up_date', $today)
            ->orderBy('follow_up_date')->get();

        $upcoming = (clone $base)->where('follow_up_completed', false)
            ->where('follow_up_date', '>', $today)
            ->orderBy('follow_up_date')->take(20)->get();

        $recentCompleted = (clone $base)->where('follow_up_completed', true)
            ->latest('updated_at')->take(20)->get();

        $shape = fn ($coll) => $coll->map(fn ($l) => $this->logPayload($l))->values();

        return response()->json([
            'data' => [
                'overdue'          => $shape($overdue),
                'today'            => $shape($todayList),
                'upcoming'         => $shape($upcoming),
                'recent_completed' => $shape($recentCompleted),
            ],
            'stats' => [
                'overdue_count'   => $overdue->count(),
                'today_count'     => $todayList->count(),
                'upcoming_count'  => $upcoming->count(),
                'completed_count' => $recentCompleted->count(),
            ],
        ]);
    }
}
