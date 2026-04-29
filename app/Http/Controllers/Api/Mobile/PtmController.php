<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\PtmBooking;
use App\Models\PtmSession;
use App\Models\PtmSlot;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentParent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mobile PTM (Parent-Teacher Meeting) endpoints.
 *
 * Mirrors App\Http\Controllers\School\PtmController but returns JSON. The
 * underlying tables (ptm_sessions / ptm_slots / ptm_bookings) and validation
 * rules are intentionally identical so the web admin and mobile admin write
 * to the same data without surprises.
 *
 * Routes are registered in routes/api.php under the
 * Route::middleware(['auth:sanctum','tenant'])->prefix('mobile') group as
 * `/mobile/ptm/*`.
 */
class PtmController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function assertAdmin(Request $request): void
    {
        $user = $request->user();
        $type = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        if (!in_array($type, ['admin', 'school_admin', 'principal', 'super_admin'], true)) {
            abort(response()->json(['error' => 'Unauthorized.'], 403));
        }
    }

    private function assertAdminOrTeacher(Request $request): void
    {
        $user = $request->user();
        $type = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        if (!in_array($type, ['admin', 'school_admin', 'principal', 'super_admin', 'teacher'], true)) {
            abort(response()->json(['error' => 'Unauthorized.'], 403));
        }
    }

    private function assertParent(Request $request): void
    {
        if (!$request->user()->isParent() && !$request->user()->isStudent()) {
            abort(response()->json(['error' => 'Unauthorized.'], 403));
        }
    }

    /**
     * Resolve which student a parent is acting on. Honours X-Active-Student-Id
     * header (or `student_id` body/query) and validates ownership.
     */
    private function resolveStudentId($user, ?Request $request = null): ?int
    {
        if ($user->isStudent()) {
            return $user->student?->id;
        }
        if ($user->isParent()) {
            $parent = $user->studentParent;
            if (!$parent) return null;
            $children = $parent->students()->pluck('id');
            if ($children->isEmpty()) return null;

            $requested = $request?->header('X-Active-Student-Id')
                      ?? $request?->input('student_id');
            if ($requested && $children->contains((int) $requested)) {
                return (int) $requested;
            }
            return (int) $children->first();
        }
        return null;
    }

    /**
     * Shape a slot for the API. When $forStudent is set, the booking field is
     * filled only if it belongs to that student so the parent UI knows which
     * slot is "theirs" without leaking other parents' data.
     */
    private function slotPayload(PtmSlot $slot, ?int $forStudent = null): array
    {
        $booking = $slot->booking;
        $bookingPayload = null;

        if ($booking) {
            $isMine = $forStudent !== null && (int) $booking->student_id === (int) $forStudent;

            if ($forStudent === null || $isMine) {
                // Admin/teacher see the full booking. Parents only see their own.
                $bookingPayload = [
                    'id'             => $booking->id,
                    'student_id'     => $booking->student_id,
                    'student_name'   => $booking->student
                        ? trim($booking->student->first_name . ' ' . $booking->student->last_name)
                        : null,
                    'parent_user_id' => $booking->parent_user_id,
                    'parent_name'    => $booking->parentUser?->name,
                    'status'         => $booking->status,
                    'meeting_notes'  => $booking->meeting_notes,
                    'is_mine'        => $isMine,
                ];
            }
        }

        $staff = $slot->staff;
        return [
            'id'         => $slot->id,
            'session_id' => $slot->session_id,
            'slot_time'  => $slot->slot_time,
            'is_booked'  => (bool) $slot->is_booked,
            'staff'      => $staff ? [
                'id'   => $staff->id,
                'name' => $staff->user?->name ?? trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? '')),
            ] : null,
            'booking'    => $bookingPayload,
        ];
    }

    private function sessionListPayload(PtmSession $session): array
    {
        return [
            'id'                    => $session->id,
            'title'                 => $session->title,
            'date'                  => $session->date instanceof \Carbon\Carbon
                ? $session->date->toDateString()
                : (string) $session->date,
            'start_time'            => $session->start_time,
            'end_time'              => $session->end_time,
            'slot_duration_minutes' => $session->slot_duration_minutes,
            'description'           => $session->description,
            'status'                => $session->status,
            'slots_count'           => $session->slots_count    ?? null,
            'bookings_count'        => $session->bookings_count ?? null,
        ];
    }

    // ── Admin endpoints ───────────────────────────────────────────────────────

    /** GET /mobile/ptm/sessions — admin: all sessions for the school */
    public function sessions(Request $request): JsonResponse
    {
        $this->assertAdmin($request);
        $schoolId = app('current_school_id');

        $sessions = PtmSession::where('school_id', $schoolId)
            ->withCount(['slots', 'bookings'])
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        return response()->json([
            'data' => $sessions->map(fn($s) => $this->sessionListPayload($s))->values(),
        ]);
    }

    /** POST /mobile/ptm/sessions — admin: create session + auto-generate slots */
    public function createSession(Request $request): JsonResponse
    {
        $this->assertAdmin($request);
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'title'                  => 'required|string|max:255',
            'date'                   => 'required|date',
            'start_time'             => 'required|date_format:H:i',
            'end_time'               => 'required|date_format:H:i|after:start_time',
            'slot_duration_minutes'  => 'required|integer|min:5|max:60',
            'description'            => 'nullable|string',
            'staff_ids'              => 'nullable|array',
            'staff_ids.*'            => 'integer|exists:staff,id',
        ]);

        $session = DB::transaction(function () use ($validated, $schoolId) {
            $session = PtmSession::create([
                'school_id'             => $schoolId,
                'title'                 => $validated['title'],
                'date'                  => $validated['date'],
                'start_time'            => $validated['start_time'],
                'end_time'              => $validated['end_time'],
                'slot_duration_minutes' => $validated['slot_duration_minutes'],
                'description'           => $validated['description'] ?? null,
                'status'                => 'draft',
            ]);

            if (!empty($validated['staff_ids'])) {
                $start    = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
                $end      = Carbon::parse($validated['date'] . ' ' . $validated['end_time']);
                $duration = $validated['slot_duration_minutes'];

                foreach ($validated['staff_ids'] as $staffId) {
                    $cursor = $start->copy();
                    while ($cursor->lt($end)) {
                        PtmSlot::create([
                            'session_id' => $session->id,
                            'staff_id'   => $staffId,
                            'slot_time'  => $cursor->format('H:i:s'),
                        ]);
                        $cursor->addMinutes($duration);
                    }
                }
            }
            return $session;
        });

        $session->loadCount(['slots', 'bookings']);
        return response()->json([
            'message' => 'PTM session created.',
            'data'    => $this->sessionListPayload($session),
        ], 201);
    }

    /** GET /mobile/ptm/sessions/{id} — admin: full detail with slots grouped by teacher */
    public function sessionDetail(Request $request, int $id): JsonResponse
    {
        $this->assertAdmin($request);
        $schoolId = app('current_school_id');

        $session = PtmSession::where('school_id', $schoolId)->find($id);
        if (!$session) {
            return response()->json(['error' => 'Session not found.'], 404);
        }
        $session->load(['slots.staff.user', 'slots.booking.student', 'slots.booking.parentUser']);

        // Group slots by staff so the UI can render a per-teacher column easily.
        $byStaff = [];
        foreach ($session->slots as $slot) {
            $staffId = $slot->staff_id;
            if (!isset($byStaff[$staffId])) {
                $byStaff[$staffId] = [
                    'staff' => [
                        'id'   => $staffId,
                        'name' => $slot->staff?->user?->name
                            ?? trim(($slot->staff->first_name ?? '') . ' ' . ($slot->staff->last_name ?? '')),
                    ],
                    'slots' => [],
                ];
            }
            $byStaff[$staffId]['slots'][] = $this->slotPayload($slot);
        }

        return response()->json([
            'data' => [
                'session'  => array_merge(
                    $this->sessionListPayload($session),
                    [
                        'slots_count'    => $session->slots->count(),
                        'bookings_count' => $session->slots->filter(fn($s) => $s->is_booked)->count(),
                    ]
                ),
                'teachers' => array_values($byStaff),
            ],
        ]);
    }

    /** PATCH /mobile/ptm/sessions/{id}/status — admin: change status */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $this->assertAdmin($request);
        $schoolId = app('current_school_id');

        $session = PtmSession::where('school_id', $schoolId)->find($id);
        if (!$session) {
            return response()->json(['error' => 'Session not found.'], 404);
        }

        $validated = $request->validate(['status' => 'required|in:draft,open,closed']);
        $session->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Session status updated.',
            'data'    => ['id' => $session->id, 'status' => $session->status],
        ]);
    }

    // ── Teacher endpoints ─────────────────────────────────────────────────────

    /** GET /mobile/ptm/teacher/bookings — bookings for the authenticated teacher */
    public function teacherBookings(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isTeacher()) {
            return response()->json(['error' => 'Only teachers can access this endpoint.'], 403);
        }
        $staff = $user->staff;
        if (!$staff) {
            return response()->json(['data' => []]);
        }

        $schoolId = app('current_school_id');

        // Bookings whose slot belongs to this teacher and whose session is in this school
        $bookings = PtmBooking::with([
                'slot.session',
                'slot.staff.user',
                'student',
                'parentUser',
            ])
            ->whereHas('slot', fn($q) => $q->where('staff_id', $staff->id))
            ->whereHas('slot.session', fn($q) => $q->where('school_id', $schoolId))
            ->get()
            ->sortBy(fn($b) => ($b->slot->session->date ?? '') . ' ' . ($b->slot->slot_time ?? ''))
            ->values();

        return response()->json([
            'data' => $bookings->map(function ($b) {
                $session = $b->slot->session;
                return [
                    'booking_id'    => $b->id,
                    'session'       => [
                        'id'    => $session->id,
                        'title' => $session->title,
                        'date'  => $session->date instanceof \Carbon\Carbon
                            ? $session->date->toDateString()
                            : (string) $session->date,
                    ],
                    'slot_time'     => $b->slot->slot_time,
                    'student'       => $b->student ? [
                        'id'   => $b->student->id,
                        'name' => trim($b->student->first_name . ' ' . $b->student->last_name),
                    ] : null,
                    'parent_name'   => $b->parentUser?->name,
                    'status'        => $b->status,
                    'meeting_notes' => $b->meeting_notes,
                ];
            })->values(),
        ]);
    }

    /** PATCH /mobile/ptm/bookings/{id}/notes — teacher (or admin): mark held / no-show + notes */
    public function addNotes(Request $request, int $bookingId): JsonResponse
    {
        $this->assertAdminOrTeacher($request);
        $schoolId = app('current_school_id');

        $booking = PtmBooking::with('slot.session')->find($bookingId);
        if (!$booking) {
            return response()->json(['error' => 'Booking not found.'], 404);
        }
        if (($booking->slot->session->school_id ?? null) !== $schoolId) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        // Teachers can only mark bookings on their own slots.
        $user = $request->user();
        if ($user->isTeacher() && (int) ($booking->slot->staff_id ?? 0) !== (int) ($user->staff?->id ?? -1)) {
            return response()->json(['error' => 'You can only update your own slots.'], 403);
        }

        $validated = $request->validate([
            'meeting_notes' => 'required|string',
            'status'        => 'required|in:completed,no_show',
        ]);
        $booking->update($validated);

        return response()->json([
            'message' => 'Meeting notes saved.',
            'data'    => [
                'booking_id'    => $booking->id,
                'status'        => $booking->status,
                'meeting_notes' => $booking->meeting_notes,
            ],
        ]);
    }

    // ── Parent endpoints ──────────────────────────────────────────────────────

    /**
     * GET /mobile/ptm/parent/sessions
     * Open future sessions with their slot grids, scoped to the active student.
     * Each slot indicates whether it's bookable and, if booked by the active
     * student, returns the booking — other parents' bookings are hidden.
     */
    public function parentSessions(Request $request): JsonResponse
    {
        $this->assertParent($request);
        $schoolId  = app('current_school_id');
        $studentId = $this->resolveStudentId($request->user(), $request);

        if (!$studentId) {
            return response()->json(['data' => [], 'student_id' => null]);
        }

        $sessions = PtmSession::where('school_id', $schoolId)
            ->where('status', 'open')
            ->where('date', '>=', now()->toDateString())
            ->with([
                'slots' => fn($q) => $q->orderBy('staff_id')->orderBy('slot_time'),
                'slots.staff.user',
                'slots.booking.student',
                'slots.booking.parentUser',
            ])
            ->orderBy('date')->orderBy('start_time')
            ->get();

        $payload = $sessions->map(function (PtmSession $s) use ($studentId) {
            $byStaff = [];
            foreach ($s->slots as $slot) {
                $staffId = $slot->staff_id;
                if (!isset($byStaff[$staffId])) {
                    $byStaff[$staffId] = [
                        'staff' => [
                            'id'   => $staffId,
                            'name' => $slot->staff?->user?->name
                                ?? trim(($slot->staff->first_name ?? '') . ' ' . ($slot->staff->last_name ?? '')),
                        ],
                        'slots' => [],
                    ];
                }
                $byStaff[$staffId]['slots'][] = $this->slotPayload($slot, $studentId);
            }
            return array_merge(
                $this->sessionListPayload($s),
                ['teachers' => array_values($byStaff)]
            );
        })->values();

        return response()->json([
            'data'       => $payload,
            'student_id' => $studentId,
        ]);
    }

    /** POST /mobile/ptm/slots/{slotId}/book — book a slot for the active student */
    public function bookSlot(Request $request, int $slotId): JsonResponse
    {
        $this->assertParent($request);
        $user      = $request->user();
        $schoolId  = app('current_school_id');
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['error' => 'No student is associated with this user.'], 422);
        }

        $slot = PtmSlot::with('session')->find($slotId);
        if (!$slot) {
            return response()->json(['error' => 'Slot not found.'], 404);
        }
        if (($slot->session->school_id ?? null) !== $schoolId) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }
        if ($slot->is_booked) {
            return response()->json(['error' => 'This slot is already booked.'], 422);
        }
        if ($slot->session->status !== 'open') {
            return response()->json(['error' => 'Bookings are not open for this session.'], 422);
        }

        // Same student can't double-book within one session (one slot per teacher per child is OK,
        // but UX-wise one booking per session per child is the typical expectation; we follow the
        // unique (slot_id, student_id) constraint and let the DB enforce the rest).
        $existing = PtmBooking::whereHas('slot', fn($q) => $q->where('session_id', $slot->session_id))
            ->where('student_id', $studentId)
            ->where('status', 'booked')
            ->exists();
        if ($existing) {
            return response()->json([
                'error' => 'This child already has a booking for this PTM session. Cancel it first to book a different slot.',
            ], 422);
        }

        $booking = DB::transaction(function () use ($slot, $studentId, $user) {
            $booking = PtmBooking::create([
                'slot_id'        => $slot->id,
                'student_id'     => $studentId,
                'parent_user_id' => $user->id,
                'status'         => 'booked',
            ]);
            $slot->update(['is_booked' => true]);
            return $booking;
        });

        return response()->json([
            'message' => 'Slot booked.',
            'data'    => [
                'booking_id' => $booking->id,
                'slot_id'    => $slot->id,
                'session_id' => $slot->session_id,
                'student_id' => $studentId,
            ],
        ], 201);
    }

    /** PATCH /mobile/ptm/bookings/{id}/cancel — parent cancels their own booking */
    public function cancelBooking(Request $request, int $bookingId): JsonResponse
    {
        $this->assertParent($request);
        $user = $request->user();

        $booking = PtmBooking::with('slot.session')->find($bookingId);
        if (!$booking) {
            return response()->json(['error' => 'Booking not found.'], 404);
        }
        if ((int) $booking->parent_user_id !== (int) $user->id) {
            return response()->json(['error' => 'You can only cancel your own bookings.'], 403);
        }
        if ($booking->status !== 'booked') {
            return response()->json([
                'error' => 'Cannot cancel a booking that is already completed or cancelled.',
            ], 422);
        }

        DB::transaction(function () use ($booking) {
            $booking->slot->update(['is_booked' => false]);
            $booking->update(['status' => 'cancelled']);
        });

        return response()->json([
            'message' => 'Booking cancelled.',
            'data'    => ['booking_id' => $booking->id, 'status' => 'cancelled'],
        ]);
    }
}
