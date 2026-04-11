<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\EditRequest;
use App\Models\Student;
use App\Models\TransportStudentAllocation;
use App\Models\TransportVehicleLiveLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationController extends Controller
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

    /**
     * Haversine distance between two GPS coordinates in km.
     */
    private function transportHaversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // ── Announcements ─────────────────────────────────────────────────────────

    public function announcements(Request $request): JsonResponse
    {
        $school = app('current_school');
        $page   = $request->integer('page', 1);

        $query = Announcement::where('school_id', $school->id)
            ->where('is_broadcasted', true)
            ->with(['sender:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('delivery_method', $request->type);
        }

        return response()->json($query->paginate(20, ['*'], 'page', $page));
    }

    // ── Transport ─────────────────────────────────────────────────────────────

    public function transport(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $locations = TransportVehicleLiveLocation::with([
            'vehicle:id,school_id,vehicle_number,vehicle_name,route_id,driver_id,conductor_name,capacity,status',
            'vehicle.route:id,route_name,route_code,start_location,end_location,distance,estimated_time',
            'vehicle.route.stops' => fn($q) => $q->orderBy('stop_order'),
            'vehicle.route.stops.studentAllocations' => fn($q) => $q->where('status', 'active'),
            'vehicle.driver:id,user_id',
            'vehicle.driver.user:id,name,phone',
        ])
        ->whereHas('vehicle', fn($q) => $q->where('school_id', $school->id))
        ->where('updated_at', '>=', now()->subMinutes(5))
        ->get();

        // Enrich each location with computed tracking data
        $locations->transform(function ($loc) {
            $vehicle = $loc->vehicle;
            $route   = $vehicle?->route;
            $stops   = $route?->stops ?? collect();

            $busLat = (float) $loc->latitude;
            $busLng = (float) $loc->longitude;
            $speed  = (float) ($loc->speed ?? 0);

            $nearestIdx  = 0;
            $nearestDist = PHP_FLOAT_MAX;

            $stopsData = $stops->values()->map(function ($stop, $idx) use ($busLat, $busLng, &$nearestIdx, &$nearestDist) {
                $dist = $this->transportHaversine($busLat, $busLng, (float) $stop->latitude, (float) $stop->longitude);
                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearestIdx  = $idx;
                }
                return [
                    'id'                   => $stop->id,
                    'name'                 => $stop->stop_name,
                    'stop_order'           => $stop->stop_order,
                    'pickup_time'          => $stop->pickup_time,
                    'drop_time'            => $stop->drop_time,
                    'latitude'             => $stop->latitude,
                    'longitude'            => $stop->longitude,
                    'student_count'        => $stop->studentAllocations->count(),
                    'distance_from_school' => $stop->distance_from_school,
                ];
            });

            // Mark stops as passed / current / upcoming
            $stopsData = $stopsData->map(function ($s, $idx) use ($nearestIdx) {
                $s['status'] = $idx < $nearestIdx ? 'passed' : ($idx === $nearestIdx ? 'current' : 'upcoming');
                return $s;
            });

            $nextStop     = $stopsData->get($nearestIdx + 1);
            $nextStopDist = $nextStop
                ? $this->transportHaversine($busLat, $busLng, (float) $nextStop['latitude'], (float) $nextStop['longitude'])
                : null;

            $etaMinutes = ($speed > 0 && $nextStopDist !== null)
                ? round(($nextStopDist / $speed) * 60)
                : null;

            $lastStop     = $stopsData->last();
            $distToSchool = $lastStop
                ? round($this->transportHaversine($busLat, $busLng, (float) $lastStop['latitude'], (float) $lastStop['longitude']), 1)
                : null;

            $loc->tracking = [
                'stops'          => $stopsData->values(),
                'nearest_stop'   => $stopsData->get($nearestIdx),
                'next_stop'      => $nextStop,
                'next_stop_dist' => $nextStopDist ? round($nextStopDist, 1) : null,
                'eta_minutes'    => $etaMinutes,
                'dist_to_school' => $distToSchool,
                'driver_name'    => $vehicle?->driver?->user?->name ?? 'N/A',
                'driver_phone'   => $vehicle?->driver?->user?->phone ?? null,
                'conductor_name' => $vehicle?->conductor_name ?? 'N/A',
                'total_students' => $stopsData->sum('student_count'),
            ];

            return $loc;
        });

        $myAllocation = null;
        $studentId    = $this->resolveStudentId($user, $request);
        if ($studentId) {
            $myAllocation = TransportStudentAllocation::where('student_id', $studentId)
                ->where('school_id', $school->id)
                ->where('status', 'active')
                ->with([
                    'route:id,route_name,route_code',
                    'stop:id,stop_name,stop_order,pickup_time,drop_time,latitude,longitude,distance_from_school',
                    'vehicle:id,vehicle_number,vehicle_name',
                ])
                ->first();
        }

        return response()->json([
            'locations'     => $locations,
            'my_allocation' => $myAllocation,
        ]);
    }

    // ── Complaints ───────────────────────────────────────────────────────────

    public function complaints(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');
        $page   = $request->input('page', 1);
        $status = $request->input('status');

        $query = Complaint::where('school_id', $school->id)
            ->where('raised_by_id', $user->id)
            ->when($status, fn($q) => $q->where('status', $status))
            ->with(['assignedDepartment:id,name', 'assignedTo:id,name'])
            ->latest();

        $complaints = $query->paginate(20, ['*'], 'page', $page);

        $data = collect($complaints->items())->map(fn($c) => [
            'id'          => $c->id,
            'subject'     => $c->type . ' Complaint',
            'description' => $c->description,
            'category'    => $c->type,
            'status'      => $c->status,
            'priority'    => $c->priority,
            'response'    => $c->resolution_notes,
            'assigned_to' => $c->assignedTo?->name,
            'department'  => $c->assignedDepartment?->name,
            'created_at'  => $c->created_at?->toIso8601String(),
            'resolved_at' => $c->resolved_at?->toIso8601String(),
        ]);

        return response()->json([
            'data'         => $data,
            'current_page' => $complaints->currentPage(),
            'last_page'    => $complaints->lastPage(),
        ]);
    }

    public function submitComplaint(Request $request): JsonResponse
    {
        $request->validate([
            'description' => 'required|string|max:2000',
            'type'        => 'required|in:Academic,Transport,Hostel,Facility,Other',
            'priority'    => 'sometimes|in:Low,Medium,High,Critical',
        ]);

        $user   = $request->user();
        $school = app('current_school');

        $complaint = Complaint::create([
            'school_id'       => $school->id,
            'type'            => $request->input('type'),
            'raised_by_type'  => get_class($user),
            'raised_by_id'    => $user->id,
            'description'     => $request->input('description'),
            'priority'        => $request->input('priority', 'Medium'),
            'status'          => 'Open',
        ]);

        return response()->json([
            'data' => [
                'id'          => $complaint->id,
                'subject'     => $complaint->type . ' Complaint',
                'description' => $complaint->description,
                'category'    => $complaint->type,
                'status'      => $complaint->status,
                'created_at'  => $complaint->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    // ── Profile Edit Requests ────────────────────────────────────────────────

    /**
     * Get student profile data for the edit-request form.
     * Returns current field values so the app can pre-fill the form.
     */
    public function editRequestForm(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['message' => 'No student found.'], 404);
        }

        $student = Student::where('id', $studentId)
            ->where('school_id', $school->id)
            ->with(['studentParent'])
            ->firstOrFail();

        return response()->json([
            'student' => [
                'id'            => $student->id,
                'first_name'    => $student->first_name,
                'last_name'     => $student->last_name,
                'dob'           => $student->dob instanceof \Carbon\Carbon ? $student->dob->toDateString() : ($student->dob ?? null),
                'birth_place'   => $student->birth_place,
                'mother_tongue' => $student->mother_tongue,
                'blood_group'   => $student->blood_group,
                'religion'      => $student->religion,
                'caste'         => $student->caste,
                'category'      => $student->category,
                'aadhaar_no'    => $student->aadhaar_no,
                'address'       => $student->address,
            ],
            'parent' => $student->studentParent ? [
                'primary_phone'     => $student->studentParent->primary_phone,
                'father_name'       => $student->studentParent->father_name,
                'mother_name'       => $student->studentParent->mother_name,
                'guardian_name'     => $student->studentParent->guardian_name,
                'father_phone'      => $student->studentParent->father_phone,
                'mother_phone'      => $student->studentParent->mother_phone,
                'father_occupation' => $student->studentParent->father_occupation,
                'mother_occupation' => $student->studentParent->mother_occupation,
                'parent_address'    => $student->studentParent->address,
            ] : null,
        ]);
    }

    /**
     * Submit a profile edit request (parent/student).
     */
    public function submitEditRequest(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['message' => 'No student found.'], 404);
        }

        $student = Student::where('id', $studentId)
            ->where('school_id', $school->id)
            ->with(['studentParent'])
            ->firstOrFail();

        // Verify ownership
        if ($user->isStudent()) {
            $own = Student::where('school_id', $school->id)->where('user_id', $user->id)->first();
            if (!$own || $own->id !== $student->id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        }
        if ($user->isParent()) {
            $isChild = Student::where('school_id', $school->id)
                ->where('id', $student->id)
                ->whereHas('studentParent', fn($q) => $q->where('user_id', $user->id))
                ->exists();
            if (!$isChild) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        }

        $validated = $request->validate([
            'first_name'        => 'nullable|string|max:255',
            'last_name'         => 'nullable|string|max:255',
            'dob'               => 'nullable|date',
            'birth_place'       => 'nullable|string|max:255',
            'mother_tongue'     => 'nullable|string|max:50',
            'blood_group'       => 'nullable|string|max:10',
            'religion'          => 'nullable|string|max:50',
            'caste'             => 'nullable|string|max:50',
            'category'          => 'nullable|string|max:50',
            'aadhaar_no'        => 'nullable|string|max:20',
            'address'           => 'nullable|string',
            'primary_phone'     => 'nullable|string|max:20',
            'father_name'       => 'nullable|string|max:255',
            'mother_name'       => 'nullable|string|max:255',
            'guardian_name'     => 'nullable|string|max:255',
            'father_phone'      => 'nullable|string|max:20',
            'mother_phone'      => 'nullable|string|max:20',
            'father_occupation' => 'nullable|string|max:255',
            'mother_occupation' => 'nullable|string|max:255',
            'parent_address'    => 'nullable|string',
            'reason'            => 'nullable|string|max:1000',
        ]);

        $reason = $validated['reason'] ?? null;
        unset($validated['reason']);

        $requestedChanges = [];
        $checkVal = function ($key, $oldVal, $newVal) use (&$requestedChanges) {
            if ($newVal === '') $newVal = null;
            if ($newVal !== null && (string) $oldVal !== (string) $newVal) {
                $requestedChanges[$key] = $newVal;
            }
        };

        // Student fields
        $checkVal('first_name',    $student->first_name,    $validated['first_name'] ?? null);
        $checkVal('last_name',     $student->last_name,     $validated['last_name'] ?? null);
        $checkVal('dob',           $student->dob?->toDateString(), $validated['dob'] ?? null);
        $checkVal('birth_place',   $student->birth_place,   $validated['birth_place'] ?? null);
        $checkVal('mother_tongue', $student->mother_tongue,  $validated['mother_tongue'] ?? null);
        $checkVal('blood_group',   $student->blood_group,   $validated['blood_group'] ?? null);
        $checkVal('religion',      $student->religion,       $validated['religion'] ?? null);
        $checkVal('caste',         $student->caste,          $validated['caste'] ?? null);
        $checkVal('category',      $student->category,       $validated['category'] ?? null);
        $checkVal('aadhaar_no',    $student->aadhaar_no,    $validated['aadhaar_no'] ?? null);
        $checkVal('address',       $student->address,        $validated['address'] ?? null);

        // Parent fields
        if ($student->studentParent) {
            $p = $student->studentParent;
            $checkVal('primary_phone',     $p->primary_phone,     $validated['primary_phone'] ?? null);
            $checkVal('father_name',       $p->father_name,       $validated['father_name'] ?? null);
            $checkVal('mother_name',       $p->mother_name,       $validated['mother_name'] ?? null);
            $checkVal('guardian_name',     $p->guardian_name,     $validated['guardian_name'] ?? null);
            $checkVal('father_phone',      $p->father_phone,      $validated['father_phone'] ?? null);
            $checkVal('mother_phone',      $p->mother_phone,      $validated['mother_phone'] ?? null);
            $checkVal('father_occupation', $p->father_occupation, $validated['father_occupation'] ?? null);
            $checkVal('mother_occupation', $p->mother_occupation, $validated['mother_occupation'] ?? null);
            $checkVal('parent_address',    $p->address,            $validated['parent_address'] ?? null);
        }

        if (empty($requestedChanges)) {
            return response()->json([
                'message' => 'No changes detected. Please modify at least one field.',
            ], 422);
        }

        $editRequest = EditRequest::create([
            'school_id'         => $school->id,
            'user_id'           => $user->id,
            'requestable_type'  => Student::class,
            'requestable_id'    => $student->id,
            'requested_changes' => $requestedChanges,
            'reason'            => $reason,
            'status'            => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile update request submitted. Pending admin approval.',
            'edit_request' => $editRequest,
        ], 201);
    }

    /**
     * List edit requests for the current student / parent's children.
     */
    public function editRequests(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $query = EditRequest::where('school_id', $school->id)
            ->where('requestable_type', Student::class)
            ->with(['reviewer:id,name'])
            ->orderByDesc('created_at');

        if ($user->isStudent()) {
            $studentId = $this->resolveStudentId($user, $request);
            if (!$studentId) return response()->json(['requests' => []]);
            $query->where('requestable_id', $studentId);
        } elseif ($user->isParent()) {
            $parent = $user->studentParent;
            if (!$parent) return response()->json(['requests' => []]);
            $childIds = $parent->students()->pluck('id');
            $query->whereIn('requestable_id', $childIds);
        } else {
            // Admin/teacher: filter by user who submitted
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->paginate(15);

        return response()->json([
            'requests' => $requests->items(),
            'total'    => $requests->total(),
            'page'     => $requests->currentPage(),
        ]);
    }
}
