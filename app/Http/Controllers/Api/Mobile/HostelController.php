<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\HostelBed;
use App\Models\HostelLeaveRequest;
use App\Models\HostelRoom;
use App\Models\HostelStudent;
use App\Models\HostelVisitor;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Mobile Hostel admin endpoints — lean v1.
 *
 * Mirrors the web Hostel\RoomController and Hostel\AllocationController
 * but only the read flows + the two most-touched write actions: assigning
 * a student to a bed and vacating an allocation. Hostel/room creation
 * and gate-pass / visitor / mess / roll-call sub-modules stay on web for
 * v1 — they're either rarely-edited setup or already covered by other
 * mobile features (front-office gate passes, visitor log).
 */
class HostelController extends Controller
{
    private function assertHostelAdmin(Request $request): void
    {
        $user = $request->user();
        $type = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        if (!in_array($type, ['admin', 'school_admin', 'principal', 'super_admin'], true)) {
            abort(response()->json(['error' => 'Unauthorized.'], 403));
        }
    }

    /**
     * GET /mobile/hostel/hostels
     *
     * List hostels with rolled-up occupancy counts so the mobile
     * Operations dashboard can show "Boys Hostel: 32/40 beds" without
     * a second round trip per building.
     */
    public function hostels(Request $request): JsonResponse
    {
        $this->assertHostelAdmin($request);
        $schoolId = app('current_school_id');

        $hostels = Hostel::where('school_id', $schoolId)
            ->with(['warden:id,name'])
            ->orderBy('name')
            ->get();

        // One query for occupancy, joined back into the per-hostel payload.
        $bedTotals = HostelBed::where('hostel_beds.school_id', $schoolId)
            ->join('hostel_rooms', 'hostel_rooms.id', '=', 'hostel_beds.hostel_room_id')
            ->selectRaw("
                hostel_rooms.hostel_id as hostel_id,
                COUNT(*) as total_beds,
                SUM(CASE WHEN hostel_beds.status='Occupied' THEN 1 ELSE 0 END) as occupied_beds,
                SUM(CASE WHEN hostel_beds.status='Maintenance' THEN 1 ELSE 0 END) as maintenance_beds
            ")
            ->groupBy('hostel_rooms.hostel_id')
            ->get()
            ->keyBy('hostel_id');

        $roomCounts = HostelRoom::where('school_id', $schoolId)
            ->selectRaw('hostel_id, COUNT(*) as room_count')
            ->groupBy('hostel_id')
            ->pluck('room_count', 'hostel_id');

        $data = $hostels->map(function ($h) use ($bedTotals, $roomCounts) {
            $row = $bedTotals->get($h->id);
            $total    = (int) ($row->total_beds       ?? 0);
            $occupied = (int) ($row->occupied_beds    ?? 0);
            $maint    = (int) ($row->maintenance_beds ?? 0);
            return [
                'id'             => $h->id,
                'name'           => $h->name,
                'type'           => $h->type,
                'address'        => $h->address,
                'intake_capacity'=> (int) ($h->intake_capacity ?? 0),
                'rooms_count'    => (int) ($roomCounts[$h->id] ?? 0),
                'beds_total'     => $total,
                'beds_occupied'  => $occupied,
                'beds_maintenance' => $maint,
                'occupancy_pct'  => $total > 0 ? round(($occupied / $total) * 100, 1) : null,
                'warden'         => $h->warden ? ['id' => $h->warden->id, 'name' => $h->warden->name] : null,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * GET /mobile/hostel/rooms
     *
     * Rooms list with eager-loaded beds + active occupant per bed. Mirrors
     * web RoomController@index but trimmed to the columns the mobile UI
     * actually renders.
     *
     * Query params:
     *   hostel_id   restrict to one hostel
     *   status      Available | Full | Maintenance
     *   q           substring on room_number
     *   per_page    5..100 (default 30)
     *   page        >=1
     */
    public function rooms(Request $request): JsonResponse
    {
        $this->assertHostelAdmin($request);
        $schoolId = app('current_school_id');

        $query = HostelRoom::where('school_id', $schoolId)
            ->with([
                'hostel:id,name,type',
                'beds:id,hostel_room_id,name,status',
                'beds.student' => fn($q) => $q->where('status', 'Active')
                    ->select('id', 'hostel_bed_id', 'student_id', 'admission_date'),
                'beds.student.student:id,first_name,last_name,admission_no',
            ]);

        if ($request->filled('hostel_id')) {
            $query->where('hostel_id', (int) $request->input('hostel_id'));
        }
        if (in_array($request->input('status'), ['Available', 'Full', 'Maintenance'], true)) {
            $query->where('status', $request->input('status'));
        }
        if ($q = trim((string) $request->input('q'))) {
            $query->where('room_number', 'like', "%{$q}%");
        }

        $perPage = max(5, min(100, (int) $request->input('per_page', 30)));
        $paginated = $query->orderBy('room_number')->paginate($perPage);

        $data = collect($paginated->items())->map(function (HostelRoom $r) {
            $beds = $r->beds ?? collect();
            $occ  = $beds->where('status', 'Occupied')->count();
            return [
                'id'          => $r->id,
                'room_number' => $r->room_number,
                'block_name'  => $r->block_name,
                'floor_name'  => $r->floor_name,
                'room_type'   => $r->room_type,
                'capacity'    => (int) $r->capacity,
                'occupied'    => $occ,
                'available'   => max(0, (int) $r->capacity - $occ),
                'cost_per_month' => (float) $r->cost_per_month,
                'status'      => $r->status,
                'hostel'      => $r->hostel ? [
                    'id'   => $r->hostel->id,
                    'name' => $r->hostel->name,
                    'type' => $r->hostel->type,
                ] : null,
                'beds' => $beds->map(function ($b) {
                    $hs = $b->student;
                    return [
                        'id'     => $b->id,
                        'name'   => $b->name,
                        'status' => $b->status,
                        'occupant' => $hs && $hs->student ? [
                            'allocation_id' => $hs->id,
                            'student_id'    => $hs->student->id,
                            'name'          => trim($hs->student->first_name . ' ' . $hs->student->last_name),
                            'admission_no'  => $hs->student->admission_no,
                            'admission_date'=> $hs->admission_date instanceof \Carbon\Carbon
                                ? $hs->admission_date->toDateString()
                                : (is_string($hs->admission_date) ? substr($hs->admission_date, 0, 10) : null),
                        ] : null,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'data'         => $data,
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
        ]);
    }

    /**
     * GET /mobile/hostel/available-beds
     *
     * Beds with status='Available' grouped by hostel, used by the
     * "Assign student" form's bed picker. Returns the bed name + room
     * label so the picker can show "Room 12 · Bed B".
     */
    public function availableBeds(Request $request): JsonResponse
    {
        $this->assertHostelAdmin($request);
        $schoolId = app('current_school_id');

        $beds = HostelBed::where('hostel_beds.school_id', $schoolId)
            ->where('hostel_beds.status', 'Available')
            ->join('hostel_rooms', 'hostel_rooms.id', '=', 'hostel_beds.hostel_room_id')
            ->join('hostels', 'hostels.id', '=', 'hostel_rooms.hostel_id')
            ->orderBy('hostels.name')
            ->orderBy('hostel_rooms.room_number')
            ->orderBy('hostel_beds.name')
            ->get([
                'hostel_beds.id', 'hostel_beds.name as bed_name',
                'hostel_rooms.id as room_id', 'hostel_rooms.room_number',
                'hostel_rooms.cost_per_month',
                'hostels.id as hostel_id', 'hostels.name as hostel_name', 'hostels.type as hostel_type',
            ]);

        // Group by hostel for the UI's section list
        $grouped = $beds->groupBy('hostel_id')->map(fn($rows) => [
            'hostel' => [
                'id'   => $rows->first()->hostel_id,
                'name' => $rows->first()->hostel_name,
                'type' => $rows->first()->hostel_type,
            ],
            'beds' => $rows->map(fn($r) => [
                'bed_id'         => $r->id,
                'bed_name'       => $r->bed_name,
                'room_id'        => $r->room_id,
                'room_number'    => $r->room_number,
                'cost_per_month' => (float) ($r->cost_per_month ?? 0),
                'label'          => "Room {$r->room_number} · {$r->bed_name}",
            ])->values(),
        ])->values();

        return response()->json(['data' => $grouped]);
    }

    /**
     * GET /mobile/hostel/allocations
     *
     * Paginated allocations list. Defaults to status=Active. Pass
     * status=Vacated or Suspended for historical lookups.
     *
     * Query params:
     *   status        Active | Vacated | Suspended (default Active)
     *   hostel_id     restrict to one hostel
     *   q             substring on student name or admission_no
     *   per_page      5..100 (default 30)
     *   page          >=1
     */
    public function allocations(Request $request): JsonResponse
    {
        $this->assertHostelAdmin($request);
        $schoolId = app('current_school_id');

        $status = in_array($request->input('status'), ['Active', 'Vacated', 'Suspended'], true)
            ? $request->input('status') : 'Active';

        $query = HostelStudent::where('school_id', $schoolId)
            ->where('status', $status)
            ->with([
                'student:id,first_name,last_name,admission_no',
                'student.studentParent:id,father_phone,mother_phone',
                'bed:id,hostel_room_id,name',
                'bed.room:id,hostel_id,room_number,cost_per_month',
                'bed.room.hostel:id,name,type',
            ]);

        if ($request->filled('hostel_id')) {
            $hid = (int) $request->input('hostel_id');
            $query->whereHas('bed.room', fn($r) => $r->where('hostel_id', $hid));
        }
        if ($q = trim((string) $request->input('q'))) {
            $query->whereHas('student', function ($s) use ($q) {
                $s->where('first_name', 'like', "%{$q}%")
                  ->orWhere('last_name', 'like', "%{$q}%")
                  ->orWhere('admission_no', 'like', "%{$q}%");
            });
        }

        $perPage = max(5, min(100, (int) $request->input('per_page', 30)));
        $paginated = $query->orderByDesc('admission_date')->paginate($perPage);

        $data = collect($paginated->items())->map(fn($a) => $this->shapeAllocation($a))->values();

        return response()->json([
            'data'         => $data,
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
        ]);
    }

    /**
     * POST /mobile/hostel/allocations
     *
     * Assign a student to a bed. Validates that the student doesn't
     * already have an active allocation and that the bed is available,
     * then flips the bed to Occupied — same rules as web AllocationController::store().
     */
    public function createAllocation(Request $request): JsonResponse
    {
        $this->assertHostelAdmin($request);
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'student_id'         => [
                'required',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'hostel_bed_id'      => [
                'required',
                Rule::exists('hostel_beds', 'id')->where('school_id', $schoolId),
            ],
            'admission_date'     => 'required|date',
            'guardian_name'      => 'nullable|string|max:255',
            'guardian_phone'     => 'nullable|string|max:255',
            'guardian_relation'  => 'nullable|string|max:255',
            'medical_info'       => 'nullable|string',
            'mess_type'          => 'required|in:Veg,Non-Veg,Custom,None',
            'months_opted'       => 'nullable|numeric|min:0',
        ]);

        // Block double-allocation
        $existing = HostelStudent::where('school_id', $schoolId)
            ->where('student_id', $validated['student_id'])
            ->where('status', 'Active')
            ->first();
        if ($existing) {
            return response()->json([
                'error' => 'This student already has an active hostel allocation. Vacate the existing allocation first or use Transfer from web.',
            ], 422);
        }

        $bed = HostelBed::where('school_id', $schoolId)->find($validated['hostel_bed_id']);
        if (!$bed || $bed->status !== 'Available') {
            return response()->json([
                'error' => 'The selected bed is not available.',
            ], 422);
        }

        $allocation = DB::transaction(function () use ($validated, $schoolId, $bed) {
            $alloc = HostelStudent::create(array_merge($validated, [
                'school_id' => $schoolId,
                'status'    => 'Active',
            ]));
            $bed->update(['status' => 'Occupied']);
            return $alloc;
        });

        $allocation->load([
            'student:id,first_name,last_name,admission_no',
            'student.studentParent:id,father_phone,mother_phone',
            'bed:id,hostel_room_id,name',
            'bed.room:id,hostel_id,room_number,cost_per_month',
            'bed.room.hostel:id,name,type',
        ]);

        return response()->json([
            'message' => 'Student allocated to bed.',
            'data'    => $this->shapeAllocation($allocation),
        ], 201);
    }

    /**
     * PATCH /mobile/hostel/allocations/{id}/vacate
     *
     * Mark an active allocation as Vacated and free the bed. Same atomic
     * transaction as web AllocationController::vacate().
     */
    public function vacateAllocation(Request $request, int $id): JsonResponse
    {
        $this->assertHostelAdmin($request);
        $schoolId = app('current_school_id');

        $allocation = HostelStudent::where('school_id', $schoolId)
            ->where('id', $id)
            ->first();
        if (!$allocation) {
            return response()->json(['error' => 'Allocation not found.'], 404);
        }
        if ($allocation->status !== 'Active') {
            return response()->json(['error' => 'Only active allocations can be vacated.'], 422);
        }

        $validated = $request->validate(['vacate_date' => 'required|date']);

        DB::transaction(function () use ($allocation, $validated) {
            $allocation->update([
                'status'      => 'Vacated',
                'vacate_date' => $validated['vacate_date'],
            ]);
            if ($allocation->bed) {
                $allocation->bed->update(['status' => 'Available']);
            }
        });

        return response()->json([
            'message' => 'Allocation vacated. Bed released.',
            'data'    => ['allocation_id' => $allocation->id, 'status' => 'Vacated'],
        ]);
    }

    private function shapeAllocation(HostelStudent $a): array
    {
        $student = $a->student;
        $bed     = $a->bed;
        $room    = $bed?->room;
        $hostel  = $room?->hostel;

        return [
            'id'              => $a->id,
            'status'          => $a->status,
            'admission_date'  => $a->admission_date instanceof \Carbon\Carbon
                ? $a->admission_date->toDateString()
                : (is_string($a->admission_date) ? substr($a->admission_date, 0, 10) : null),
            'vacate_date'     => $a->vacate_date instanceof \Carbon\Carbon
                ? $a->vacate_date->toDateString()
                : (is_string($a->vacate_date) ? substr($a->vacate_date, 0, 10) : null),
            'guardian_name'   => $a->guardian_name,
            'guardian_phone'  => $a->guardian_phone,
            'guardian_relation' => $a->guardian_relation,
            'mess_type'       => $a->mess_type,
            'hostel_fee'      => (float) ($a->hostel_fee ?? 0),
            'amount_paid'     => (float) ($a->amount_paid ?? 0),
            'balance'         => (float) ($a->balance ?? 0),
            'payment_status'  => $a->payment_status,
            'student' => $student ? [
                'id'           => $student->id,
                'name'         => trim($student->first_name . ' ' . $student->last_name),
                'admission_no' => $student->admission_no,
                'father_phone' => $student->studentParent?->father_phone,
                'mother_phone' => $student->studentParent?->mother_phone,
            ] : null,
            'bed' => $bed ? [
                'id'   => $bed->id,
                'name' => $bed->name,
            ] : null,
            'room' => $room ? [
                'id'             => $room->id,
                'room_number'    => $room->room_number,
                'cost_per_month' => (float) ($room->cost_per_month ?? 0),
            ] : null,
            'hostel' => $hostel ? [
                'id'   => $hostel->id,
                'name' => $hostel->name,
                'type' => $hostel->type,
            ] : null,
        ];
    }

    // ── Gate Passes (HostelLeaveRequest) ──────────────────────────────────────

    private function gatePassPayload(HostelLeaveRequest $g): array
    {
        $today = now()->toDateString();
        $bucket = 'upcoming';
        if ($g->status === 'returned')           $bucket = 'returned';
        elseif ($g->status === 'rejected')       $bucket = 'rejected';
        elseif ($g->actual_out_time && !$g->actual_in_time) $bucket = 'out';
        elseif ($g->to_date && $g->to_date < $today && $g->status !== 'returned') $bucket = 'overdue';
        elseif ($g->status === 'pending')        $bucket = 'pending';
        elseif ($g->status === 'approved')       $bucket = 'approved';

        return [
            'id'              => $g->id,
            'student'         => $g->student ? [
                'id'           => $g->student->id,
                'name'         => trim(($g->student->first_name ?? '') . ' ' . ($g->student->last_name ?? '')),
                'admission_no' => $g->student->admission_no,
            ] : null,
            'leave_type'      => $g->leave_type,
            'from_date'       => $g->from_date,
            'to_date'         => $g->to_date,
            'destination'     => $g->destination,
            'reason'          => $g->reason,
            'status'          => $g->status,
            'bucket'          => $bucket,
            'escort_name'     => $g->escort_name,
            'escort_phone'    => $g->escort_phone,
            'escort_relation' => $g->escort_relation,
            'parent_approval' => (bool) $g->parent_approval,
            'actual_out_time' => $g->actual_out_time?->toIso8601String(),
            'actual_in_time'  => $g->actual_in_time?->toIso8601String(),
            'approver'        => $g->approver ? ['id' => $g->approver->id, 'name' => $g->approver->name] : null,
            'created_at'      => $g->created_at?->toIso8601String(),
        ];
    }

    /**
     * GET /mobile/hostel/gate-passes
     *
     * Query params: status (pending|approved|out|returned|rejected|all), q (student name/admission), per_page.
     */
    public function gatePasses(Request $request): JsonResponse
    {
        $this->assertHostelAdmin($request);
        $schoolId = app('current_school_id');

        $status  = (string) $request->input('status', 'all');
        $q       = trim((string) $request->input('q', ''));
        $perPage = max(5, min(100, (int) $request->input('per_page', 30)));

        $query = HostelLeaveRequest::where('school_id', $schoolId)
            ->with(['student:id,first_name,last_name,admission_no,school_id', 'approver:id,name'])
            ->latest();

        if ($status === 'out') {
            $query->whereNotNull('actual_out_time')->whereNull('actual_in_time');
        } elseif (in_array($status, ['pending', 'approved', 'rejected', 'returned'], true)) {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $query->whereHas('student', function ($s) use ($q) {
                $s->where('first_name', 'like', "%{$q}%")
                  ->orWhere('last_name', 'like', "%{$q}%")
                  ->orWhere('admission_no', 'like', "%{$q}%");
            });
        }

        $paginated = $query->paginate($perPage);

        // Tab counters
        $today  = now()->toDateString();
        $counts = [
            'pending'  => HostelLeaveRequest::where('school_id', $schoolId)->where('status', 'pending')->count(),
            'approved' => HostelLeaveRequest::where('school_id', $schoolId)->where('status', 'approved')->count(),
            'out'      => HostelLeaveRequest::where('school_id', $schoolId)
                              ->whereNotNull('actual_out_time')->whereNull('actual_in_time')->count(),
            'overdue'  => HostelLeaveRequest::where('school_id', $schoolId)
                              ->whereNotNull('actual_out_time')->whereNull('actual_in_time')
                              ->where('to_date', '<', $today)->count(),
        ];

        return response()->json([
            'data'         => collect($paginated->items())->map(fn ($g) => $this->gatePassPayload($g))->values(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
            'summary'      => $counts,
        ]);
    }

    /**
     * POST /mobile/hostel/gate-passes
     */
    public function createGatePass(Request $request): JsonResponse
    {
        $this->assertHostelAdmin($request);
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'student_id'      => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'leave_type'      => 'required|in:Day-out,Home-leave,Medical,Other',
            'from_date'       => 'required|date',
            'to_date'         => 'required|date|after_or_equal:from_date',
            'destination'     => 'nullable|string|max:255',
            'reason'          => 'required|string|max:1000',
            'escort_name'     => 'nullable|string|max:120',
            'escort_phone'    => 'nullable|string|max:20',
            'escort_relation' => 'nullable|string|max:60',
            'status'          => 'nullable|in:pending,approved',
        ]);

        $g = new HostelLeaveRequest($validated);
        $g->school_id = $schoolId;
        $g->status    = $validated['status'] ?? 'pending';
        if ($g->status === 'approved') {
            $g->approved_by = $request->user()->id;
        }
        $g->save();
        $g->load(['student:id,first_name,last_name,admission_no,school_id', 'approver:id,name']);

        return response()->json(['message' => 'Gate pass created.', 'data' => $this->gatePassPayload($g)], 201);
    }

    /**
     * PATCH /mobile/hostel/gate-passes/{id}/status
     * Body: { action: approve|reject|out|return, [late_reason] }
     */
    public function updateGatePassStatus(Request $request, int $id): JsonResponse
    {
        $this->assertHostelAdmin($request);
        $schoolId = app('current_school_id');

        $g = HostelLeaveRequest::where('school_id', $schoolId)->find($id);
        if (!$g) return response()->json(['error' => 'Gate pass not found.'], 404);

        $validated = $request->validate([
            'action'      => 'required|in:approve,reject,out,return',
            'late_reason' => 'nullable|string|max:500',
        ]);

        $now = Carbon::now();
        switch ($validated['action']) {
            case 'approve':
                if ($g->status !== 'pending') return response()->json(['error' => 'Only pending passes can be approved.'], 422);
                $g->status      = 'approved';
                $g->approved_by = $request->user()->id;
                break;
            case 'reject':
                if ($g->status !== 'pending') return response()->json(['error' => 'Only pending passes can be rejected.'], 422);
                $g->status      = 'rejected';
                $g->approved_by = $request->user()->id;
                break;
            case 'out':
                if ($g->status !== 'approved') return response()->json(['error' => 'Pass must be approved before exit.'], 422);
                if ($g->actual_out_time)       return response()->json(['error' => 'Already marked exited.'], 422);
                $g->actual_out_time = $now;
                break;
            case 'return':
                if (!$g->actual_out_time) return response()->json(['error' => 'Pass has not been used yet.'], 422);
                if ($g->actual_in_time)   return response()->json(['error' => 'Already marked returned.'], 422);
                $g->actual_in_time = $now;
                $g->status         = 'returned';
                if (!empty($validated['late_reason'])) {
                    $g->late_reason = $validated['late_reason'];
                }
                break;
        }
        $g->save();
        $g->load(['student:id,first_name,last_name,admission_no,school_id', 'approver:id,name']);

        return response()->json(['message' => 'Gate pass updated.', 'data' => $this->gatePassPayload($g)]);
    }

    // ── Visitors (HostelVisitor) ──────────────────────────────────────────────

    private function visitorPayload(HostelVisitor $v): array
    {
        return [
            'id'            => $v->id,
            'visitor_name'  => $v->visitor_name,
            'relation'      => $v->relation,
            'phone'         => $v->phone,
            'visitor_type'  => $v->visitor_type,
            'meet_user_type'=> $v->meet_user_type,
            'visitor_count' => (int) ($v->visitor_count ?? 1),
            'date'          => $v->date,
            'in_time'       => $v->in_time,
            'out_time'      => $v->out_time,
            'purpose'       => $v->purpose,
            'id_proof_type' => $v->id_proof_type,
            'remarks'       => $v->remarks,
            'is_approved'   => (bool) $v->is_approved,
            'student'       => $v->student ? [
                'id'           => $v->student->id,
                'name'         => trim(($v->student->first_name ?? '') . ' ' . ($v->student->last_name ?? '')),
                'admission_no' => $v->student->admission_no,
            ] : null,
            'staff'         => $v->staff ? [
                'id'   => $v->staff->id,
                'name' => $v->staff->user?->name ?? ('Staff #' . $v->staff->id),
            ] : null,
            'created_at'    => $v->created_at?->toIso8601String(),
        ];
    }

    /**
     * GET /mobile/hostel/visitors
     * Query params: scope (today|active|all), q, per_page.
     */
    public function visitors(Request $request): JsonResponse
    {
        $this->assertHostelAdmin($request);
        $schoolId = app('current_school_id');

        $scope   = (string) $request->input('scope', 'today');
        $q       = trim((string) $request->input('q', ''));
        $perPage = max(5, min(100, (int) $request->input('per_page', 30)));

        $query = HostelVisitor::where('school_id', $schoolId)
            ->with(['student:id,first_name,last_name,admission_no,school_id', 'staff.user:id,name'])
            ->latest();

        $today = now()->toDateString();
        if ($scope === 'today') {
            $query->where('date', $today);
        } elseif ($scope === 'active') {
            $query->where('date', $today)->whereNull('out_time');
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('visitor_name', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%")
                  ->orWhereHas('student', fn ($s) => $s
                      ->where('first_name', 'like', "%{$q}%")
                      ->orWhere('last_name', 'like', "%{$q}%")
                      ->orWhere('admission_no', 'like', "%{$q}%"));
            });
        }

        $paginated = $query->paginate($perPage);

        $counts = [
            'today'  => HostelVisitor::where('school_id', $schoolId)->where('date', $today)->count(),
            'active' => HostelVisitor::where('school_id', $schoolId)->where('date', $today)->whereNull('out_time')->count(),
            'total'  => HostelVisitor::where('school_id', $schoolId)->count(),
        ];

        return response()->json([
            'data'         => collect($paginated->items())->map(fn ($v) => $this->visitorPayload($v))->values(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
            'summary'      => $counts,
        ]);
    }

    /**
     * POST /mobile/hostel/visitors  — log a new check-in.
     */
    public function logVisitor(Request $request): JsonResponse
    {
        $this->assertHostelAdmin($request);
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'visitor_name'  => 'required|string|max:120',
            'relation'      => 'nullable|string|max:60',
            'phone'         => 'nullable|string|max:20',
            'date'          => 'nullable|date',
            'in_time'       => 'nullable|string|max:8',
            'purpose'       => 'nullable|string|max:255',
            'id_proof_type' => 'nullable|string|max:60',
            'visitor_count' => 'nullable|integer|min:1|max:20',
            'meet_user_type'=> 'nullable|in:student,staff',
            'student_id'    => ['nullable', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'staff_id'      => ['nullable', Rule::exists('staff', 'id')->where('school_id', $schoolId)],
            'remarks'       => 'nullable|string|max:1000',
        ]);

        $v = new HostelVisitor($validated);
        $v->school_id     = $schoolId;
        $v->date          = $validated['date']    ?? now()->toDateString();
        $v->in_time       = $validated['in_time'] ?? now()->format('H:i');
        $v->visitor_count = $validated['visitor_count'] ?? 1;
        $v->is_approved   = true;
        $v->save();
        $v->load(['student:id,first_name,last_name,admission_no,school_id', 'staff.user:id,name']);

        return response()->json(['message' => 'Visitor logged.', 'data' => $this->visitorPayload($v)], 201);
    }

    /**
     * PATCH /mobile/hostel/visitors/{id}/checkout
     */
    public function checkoutVisitor(Request $request, int $id): JsonResponse
    {
        $this->assertHostelAdmin($request);
        $schoolId = app('current_school_id');

        $v = HostelVisitor::where('school_id', $schoolId)->find($id);
        if (!$v)          return response()->json(['error' => 'Visitor entry not found.'], 404);
        if ($v->out_time) return response()->json(['error' => 'Already checked out.'], 422);

        $v->out_time = now()->format('H:i');
        $v->save();
        $v->load(['student:id,first_name,last_name,admission_no,school_id', 'staff.user:id,name']);

        return response()->json(['message' => 'Visitor checked out.', 'data' => $this->visitorPayload($v)]);
    }
}
