<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\EditRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Admin / principal review queue for parent-submitted edit requests.
 *
 * Mirrors App\Http\Controllers\School\EditRequestController on the web —
 * same approve/reject business rules (incl. routing parent-only fields
 * to the related parents row instead of the requestable Student model),
 * shaped for the mobile UI as JSON instead of Inertia.
 */
class EditRequestAdminController extends Controller
{
    /** Keys that live on the related `parents` row, not the requestable Student. */
    private const PARENT_KEYS = [
        'primary_phone', 'father_name', 'mother_name', 'guardian_name',
        'guardian_email', 'guardian_phone',
        'father_phone', 'mother_phone',
        'father_occupation', 'father_qualification',
        'mother_occupation', 'mother_qualification',
        'parent_address',
    ];

    /** Keys that live on the related User row. */
    private const USER_KEYS = ['name', 'phone', 'email'];

    private function assertReviewer(Request $request): void
    {
        $user = $request->user();
        $type = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        if (!in_array($type, ['admin', 'school_admin', 'principal', 'super_admin'], true)) {
            abort(response()->json(['error' => 'Unauthorized.'], 403));
        }
    }

    private function readableType(?string $type): string
    {
        return $type && str_contains($type, 'Student') ? 'Student' : 'Staff';
    }

    /**
     * Build the side-by-side diff. For each requested key, fetch the current
     * value from User / parents / requestable as appropriate.
     */
    private function buildDiff(EditRequest $req): array
    {
        $diff  = [];
        $model = $req->requestable;
        if (!$model) {
            return $diff;
        }
        $changes = is_array($req->requested_changes) ? $req->requested_changes : [];

        $parent = method_exists($model, 'studentParent') ? $model->studentParent : null;

        foreach ($changes as $key => $newValue) {
            $oldValue = null;
            if (in_array($key, self::USER_KEYS, true) && $model->user) {
                $oldValue = $model->user->{$key} ?? null;
            } elseif (in_array($key, self::PARENT_KEYS, true) && $parent) {
                $col = $key === 'parent_address' ? 'address' : $key;
                $oldValue = $parent->{$col} ?? null;
            } else {
                $oldValue = $model->{$key} ?? null;
            }
            $diff[$key] = ['old' => $oldValue, 'new' => $newValue];
        }
        return $diff;
    }

    private function reqPayload(EditRequest $req, bool $withDiff = false): array
    {
        $req->loadMissing(['user', 'requestable.user', 'reviewer']);

        $subject = null;
        if ($req->requestable) {
            $m = $req->requestable;
            $name = $m->user?->name
                ?? trim(($m->first_name ?? '') . ' ' . ($m->last_name ?? ''));
            $subject = [
                'id'           => $m->id,
                'type'         => $this->readableType($req->requestable_type),
                'name'         => $name ?: 'Unknown',
                'admission_no' => $m->admission_no ?? null,
            ];
        }

        $payload = [
            'id'          => $req->id,
            'status'      => $req->status,
            'reason'      => $req->reason,
            'rejection'   => $req->rejection_reason,
            'submitted_by'=> $req->user ? [
                'id'   => $req->user->id,
                'name' => $req->user->name,
            ] : null,
            'subject'     => $subject,
            'reviewer'    => $req->reviewer ? [
                'id'   => $req->reviewer->id,
                'name' => $req->reviewer->name,
            ] : null,
            'reviewed_at' => $req->reviewed_at?->toIso8601String(),
            'created_at'  => $req->created_at?->toIso8601String(),
            'field_count' => is_array($req->requested_changes) ? count($req->requested_changes) : 0,
        ];

        if ($withDiff) {
            $payload['diff'] = $this->buildDiff($req);
        }

        return $payload;
    }

    /**
     * GET /mobile/admin/edit-requests
     *
     * Query params:
     *   status   pending | approved | rejected | all  (default pending)
     *   page     >= 1
     *   per_page 5..100 (default 30)
     */
    public function index(Request $request): JsonResponse
    {
        $this->assertReviewer($request);
        $schoolId = app('current_school_id');

        $status  = (string) $request->input('status', 'pending');
        $perPage = max(5, min(100, (int) $request->input('per_page', 30)));

        $query = EditRequest::where('school_id', $schoolId)
            ->with(['user', 'requestable.user', 'reviewer'])
            ->latest();

        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $paginated = $query->paginate($perPage);

        // Summary counts per status — used for the tab header tiles
        $counts = EditRequest::where('school_id', $schoolId)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return response()->json([
            'data'         => collect($paginated->items())
                ->map(fn ($r) => $this->reqPayload($r))
                ->values(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'summary'      => [
                'pending'  => (int) ($counts['pending']  ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
                'total'    => array_sum($counts),
            ],
        ]);
    }

    /**
     * GET /mobile/admin/edit-requests/{id}
     * Returns the request including the side-by-side diff.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->assertReviewer($request);
        $schoolId = app('current_school_id');

        $req = EditRequest::where('school_id', $schoolId)->find($id);
        if (!$req) {
            return response()->json(['error' => 'Edit request not found.'], 404);
        }
        return response()->json(['data' => $this->reqPayload($req, true)]);
    }

    /**
     * POST /mobile/admin/edit-requests/{id}/approve
     * Apply the requested changes — same routing logic the web controller
     * uses (User keys / parent keys / requestable keys), wrapped in a tx.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $this->assertReviewer($request);
        $schoolId = app('current_school_id');

        $req = EditRequest::where('school_id', $schoolId)->find($id);
        if (!$req)                          return response()->json(['error' => 'Edit request not found.'], 404);
        if ($req->status !== 'pending')     return response()->json(['error' => 'This request has already been processed.'], 422);
        if (!$req->requestable)             return response()->json(['error' => 'The associated record no longer exists.'], 404);

        $model   = $req->requestable;
        $changes = is_array($req->requested_changes) ? $req->requested_changes : [];

        DB::transaction(function () use ($model, $changes, $req) {
            $userUpdates   = [];
            $modelUpdates  = [];
            $parentUpdates = [];

            foreach ($changes as $key => $value) {
                if (in_array($key, self::PARENT_KEYS, true)) {
                    $col = $key === 'parent_address' ? 'address' : $key;
                    $parentUpdates[$col] = $value;
                } elseif (in_array($key, self::USER_KEYS, true) && $model->user) {
                    $userUpdates[$key] = $value;
                } else {
                    $modelUpdates[$key] = $value;
                }
            }

            if (!empty($userUpdates) && $model->user) {
                $model->user->update($userUpdates);
            }
            if (!empty($modelUpdates)) {
                $model->update($modelUpdates);
            }
            if (!empty($parentUpdates) && method_exists($model, 'studentParent') && $model->studentParent) {
                $model->studentParent->update($parentUpdates);
            }

            $req->update([
                'status'      => 'approved',
                'reviewed_by' => $req->school_id === app('current_school_id') ? auth()->id() : null,
                'reviewed_at' => Carbon::now(),
            ]);
        });

        $req->refresh()->load(['user', 'requestable.user', 'reviewer']);
        return response()->json([
            'message' => 'Edit request approved and changes applied.',
            'data'    => $this->reqPayload($req, true),
        ]);
    }

    /**
     * POST /mobile/admin/edit-requests/{id}/reject
     * Body: { rejection_reason: string }
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $this->assertReviewer($request);
        $schoolId = app('current_school_id');

        $req = EditRequest::where('school_id', $schoolId)->find($id);
        if (!$req)                       return response()->json(['error' => 'Edit request not found.'], 404);
        if ($req->status !== 'pending')  return response()->json(['error' => 'This request has already been processed.'], 422);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $req->update([
            'status'           => 'rejected',
            'reviewed_by'     => auth()->id(),
            'reviewed_at'     => Carbon::now(),
            'rejection_reason'=> $validated['rejection_reason'],
        ]);

        $req->refresh()->load(['user', 'requestable.user', 'reviewer']);
        return response()->json([
            'message' => 'Edit request rejected.',
            'data'    => $this->reqPayload($req, true),
        ]);
    }
}
