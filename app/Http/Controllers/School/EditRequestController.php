<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\CourseClass;
use App\Models\EditRequest;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

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

        // For class/section diffs we need the current-year academic history
        // row's "before" values (those columns aren't on the Student model).
        $history = null;
        if ($model instanceof Student && app()->bound('current_academic_year_id')) {
            $history = StudentAcademicHistory::where('student_id', $model->id)
                ->where('school_id', $model->school_id)
                ->where('academic_year_id', app('current_academic_year_id'))
                ->latest('id')->first();
        }

        // ID-typed keys whose raw values aren't useful in a diff — translate
        // them to readable names for the approver.
        $idResolvers = [
            'class_id'   => fn($id) => $id ? (CourseClass::find($id)?->name ?? "#{$id}") : null,
            'section_id' => fn($id) => $id ? (Section::find($id)?->name ?? "#{$id}") : null,
        ];

        foreach ($changes as $key => $newValue) {
            // Photo: resolve both sides to full public URLs so the Vue
            // component can render them as <img> thumbnails.
            if ($key === 'photo') {
                $diff[$key] = [
                    'old'      => $model->photo ? asset('storage/' . $model->photo) : null,
                    'new'      => asset('storage/' . $newValue),
                    'is_photo' => true,
                ];
                continue;
            }

            // Check if key is on the user model (like phone/name) or the student/staff model
            if (in_array($key, ['name', 'phone', 'email']) && $model->user) {
                $oldValue = $model->user->$key;
            } elseif (in_array($key, ['class_id', 'section_id'], true)) {
                // Old values for academic-history fields come from the
                // current-year history row, not from $model directly.
                $oldValue = $history?->{$key};
            } else {
                $oldValue = $model->$key;
            }

            if (isset($idResolvers[$key])) {
                $oldValue = $idResolvers[$key]($oldValue);
                $newValue = $idResolvers[$key]($newValue);
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

        // Keys that live on the `parents` table (StudentParent), not on the
        // requestable model itself. The Student request-edit form pools both
        // student and parent fields into one flat dict keyed by their column
        // names; without this routing, parent-field edits get silently dropped
        // because they aren't in Student::$fillable.
        $parentKeys = [
            'primary_phone', 'father_name', 'mother_name', 'guardian_name',
            'guardian_email', 'guardian_phone',
            'father_phone', 'mother_phone',
            'father_occupation', 'father_qualification',
            'mother_occupation', 'mother_qualification',
            // 'parent_address' is the form key — the column on `parents` is
            // just `address`, so it gets mapped explicitly below.
            'parent_address',
        ];

        // Keys that live on the current-year StudentAcademicHistory row
        // (not on the Student model). Photo-Numbers / Photographer flow
        // can request class / section transfers, which are applied here
        // on approval.
        $historyKeys = ['class_id', 'section_id'];

        DB::transaction(function () use ($model, $changes, $editRequest, $parentKeys, $historyKeys) {
            $userUpdates    = [];
            $modelUpdates   = [];
            $parentUpdates  = [];
            $historyUpdates = [];

            foreach ($changes as $key => $value) {
                if ($key === 'photo') {
                    // Move the pending photo from the temp folder to the
                    // permanent students/photos/ location, then update the model.
                    if ($model->photo) {
                        Storage::disk('public')->delete($model->photo);
                    }
                    $newPath = 'students/photos/' . basename($value);
                    Storage::disk('public')->move($value, $newPath);
                    $model->update(['photo' => $newPath]);
                } elseif (in_array($key, $parentKeys, true)) {
                    // Translate the form-only key 'parent_address' into the
                    // actual column 'address' on the parents table.
                    $col = $key === 'parent_address' ? 'address' : $key;
                    $parentUpdates[$col] = $value;
                } elseif (in_array($key, $historyKeys, true)) {
                    $historyUpdates[$key] = $value;
                } elseif (in_array($key, ['name', 'phone', 'email'], true) && $model->user) {
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

            // Apply parent updates only when the model has a related parent
            // (i.e. requestable is a Student with studentParent loaded).
            if (!empty($parentUpdates) && method_exists($model, 'studentParent') && $model->studentParent) {
                $model->studentParent->update($parentUpdates);
            }

            // Apply class/section changes to the student's CURRENT-YEAR
            // academic history row. If there's no current-year row (e.g. the
            // student isn't enrolled this year), drop the change silently —
            // the request shouldn't have been approvable in that state.
            if (!empty($historyUpdates) && $model instanceof Student && app()->bound('current_academic_year_id')) {
                $history = StudentAcademicHistory::where('student_id', $model->id)
                    ->where('school_id', $model->school_id)
                    ->where('academic_year_id', app('current_academic_year_id'))
                    ->latest('id')->first();

                if ($history) {
                    // Re-validate that section belongs to the (possibly new)
                    // class at approval time too — admin records may have
                    // shifted between request and approve.
                    if (isset($historyUpdates['section_id'])) {
                        $intendedClassId = $historyUpdates['class_id'] ?? $history->class_id;
                        $sectionBelongs = Section::where('id', $historyUpdates['section_id'])
                            ->where('course_class_id', $intendedClassId)
                            ->exists();
                        if (! $sectionBelongs) {
                            // Drop the section change; keep the class change
                            // (if any). Surface to admin via flash later.
                            unset($historyUpdates['section_id']);
                        }
                    }

                    $history->update($historyUpdates);
                }
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

        // Clean up any pending temp photo so it doesn't orphan on disk.
        if (isset($editRequest->requested_changes['photo'])) {
            Storage::disk('public')->delete($editRequest->requested_changes['photo']);
        }

        $editRequest->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => Carbon::now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return redirect()->route('school.edit-requests.index')->with('success', 'Edit request rejected.');
    }
}
