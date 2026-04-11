<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Manages leave types scoped to students (applicable_to = 'student').
 * Reuses the leave_types table with an applicable_to discriminator.
 */
class StudentLeaveTypeController extends Controller
{
    private function baseQuery()
    {
        return LeaveType::where('school_id', app('current_school_id'))
            ->whereIn('applicable_to', ['student', 'both']);
    }

    public function index()
    {
        $leaveTypes = $this->baseQuery()
            ->withCount('studentLeaves')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('School/Students/Leaves/Types', [
            'leaveTypes' => $leaveTypes,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'name'                   => 'required|string|max:100',
            'code'                   => ['required', 'string', 'max:10',
                Rule::unique('leave_types')->where('school_id', $schoolId)],
            'days_allowed'           => 'required|integer|min:0|max:365',
            'color'                  => 'required|string|max:7',
            'requires_document'      => 'boolean',
            'min_notice_days'        => 'integer|min:0',
            'description'            => 'nullable|string|max:500',
            'sort_order'             => 'integer|min:0',
        ]);

        $validated['school_id']     = $schoolId;
        $validated['code']          = strtoupper($validated['code']);
        $validated['applicable_to'] = 'student';
        $validated['is_active']     = true;
        $validated['is_paid']       = false;
        $validated['carry_forward'] = false;
        // Auto-assign next sort order if not provided
        if (!isset($validated['sort_order'])) {
            $validated['sort_order'] = (LeaveType::where('school_id', $schoolId)->max('sort_order') ?? -1) + 1;
        }

        LeaveType::create($validated);

        return back()->with('success', "Leave type '{$validated['name']}' created.");
    }

    public function update(Request $request, LeaveType $studentLeaveType)
    {
        $schoolId = app('current_school_id');

        abort_if($studentLeaveType->school_id !== $schoolId, 403);

        $validated = $request->validate([
            'name'              => 'required|string|max:100',
            'code'              => ['required', 'string', 'max:10',
                Rule::unique('leave_types')->where('school_id', $schoolId)->ignore($studentLeaveType->id)],
            'days_allowed'      => 'required|integer|min:0|max:365',
            'color'             => 'required|string|max:7',
            'requires_document' => 'boolean',
            'min_notice_days'   => 'integer|min:0',
            'is_active'         => 'boolean',
            'description'       => 'nullable|string|max:500',
        ]);

        $validated['code'] = strtoupper($validated['code']);

        $studentLeaveType->update($validated);

        return back()->with('success', "Leave type '{$studentLeaveType->name}' updated.");
    }

    public function destroy(LeaveType $studentLeaveType)
    {
        abort_if($studentLeaveType->school_id !== app('current_school_id'), 403);

        if ($studentLeaveType->studentLeaves()->exists()) {
            return back()->with('error', 'Cannot delete: this leave type has existing records. Deactivate it instead.');
        }

        $studentLeaveType->delete();
        return back()->with('success', 'Leave type deleted.');
    }

    public function toggle(LeaveType $studentLeaveType)
    {
        abort_if($studentLeaveType->school_id !== app('current_school_id'), 403);

        $studentLeaveType->update(['is_active' => !$studentLeaveType->is_active]);
        $status = $studentLeaveType->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Leave type {$status}.");
    }

    public function reorder(Request $request)
    {
        $schoolId = app('current_school_id');
        $request->validate(['order' => 'required|array', 'order.*' => 'integer']);

        foreach ($request->order as $position => $id) {
            LeaveType::where('id', $id)->where('school_id', $schoolId)
                ->update(['sort_order' => $position]);
        }

        return back()->with('success', 'Order saved.');
    }
}
