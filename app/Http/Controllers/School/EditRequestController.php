<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\EditRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Carbon;

class EditRequestController extends Controller
{
    /**
     * Display a listing of edit requests.
     */
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        
        $editRequests = EditRequest::tenant()
            ->with(['user', 'requestable.user']) // Requestable could be Student or Staff
            ->where('status', $status)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('School/EditRequests/Index', [
            'editRequests' => $editRequests,
            'filters' => ['status' => $status],
        ]);
    }

    /**
     * Helper mapping polymorphic types to readable names
     */
    private function getReadableType($type) {
        return str_contains($type, 'Student') ? 'Student' : 'Staff';
    }

    /**
     * Show the specific edit request detail / comparison diff.
     */
    public function show(EditRequest $editRequest)
    {
        // Ensure tenant scope
        if ($editRequest->school_id !== app('current_school_id')) {
            abort(403);
        }

        $editRequest->load(['user', 'requestable.user', 'reviewer']);

        // Build a side-by-side array
        $diff = [];
        $model = $editRequest->requestable;
        $changes = $editRequest->requested_changes;

        foreach ($changes as $key => $newValue) {
            // Check if key is on the user model (like phone/name) or the student/staff model
            if (in_array($key, ['name', 'phone', 'email']) && $model->user) {
                $oldValue = $model->user->$key;
            } else {
                $oldValue = $model->$key;
            }

            $diff[$key] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return Inertia::render('School/EditRequests/Show', [
            'editRequest' => $editRequest,
            'requestType' => $this->getReadableType($editRequest->requestable_type),
            'diff' => $diff
        ]);
    }

    /**
     * Approve the edit request and apply the changes.
     */
    public function approve(Request $request, EditRequest $editRequest)
    {
        if ($editRequest->school_id !== app('current_school_id')) {
            abort(403);
        }

        if ($editRequest->status !== 'pending') {
            return back()->with('error', 'This request has already been processed.');
        }

        $model = $editRequest->requestable;
        abort_if(!$model, 404, 'The associated record no longer exists.');

        $changes = $editRequest->requested_changes;

        DB::transaction(function () use ($model, $changes, $editRequest) {
            $userUpdates = [];
            $modelUpdates = [];

            foreach ($changes as $key => $value) {
                if (in_array($key, ['name', 'phone', 'email']) && $model->user) {
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

            $editRequest->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => Carbon::now(),
            ]);
        });

        return redirect()->route('school.edit-requests.index')->with('success', 'Edit request approved and changes applied successfully.');
    }

    /**
     * Reject the edit request.
     */
    public function reject(Request $request, EditRequest $editRequest)
    {
        if ($editRequest->school_id !== app('current_school_id')) {
            abort(403);
        }

        if ($editRequest->status !== 'pending') {
            return back()->with('error', 'This request has already been processed.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000'
        ]);

        $editRequest->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => Carbon::now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return redirect()->route('school.edit-requests.index')->with('success', 'Edit request rejected.');
    }
}
