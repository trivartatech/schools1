<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\HostelBed;
use App\Models\HostelRoom;
use App\Models\HostelStudent;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
