<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaveTypeController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school_id');

        $leaveTypes = LeaveType::where('school_id', $schoolId)
            ->withCount('leaves')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('School/Staff/Leaves/Types/Index', [
            'leaveTypes' => $leaveTypes,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'name'                   => 'required|string|max:100',
            'code'                   => ['required', 'string', 'max:10',
                \Illuminate\Validation\Rule::unique('leave_types')->where('school_id', $schoolId)],
            'days_allowed'           => 'required|integer|min:0|max:365',
            'color'                  => 'required|string|max:7',
            'is_paid'                => 'boolean',
            'carry_forward'          => 'boolean',
            'max_carry_forward_days' => 'integer|min:0',
            'requires_document'      => 'boolean',
            'min_notice_days'        => 'integer|min:0',
            'description'            => 'nullable|string|max:500',
            'sort_order'             => 'integer|min:0',
        ]);

        $validated['school_id'] = $schoolId;
        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = true;

        LeaveType::create($validated);

        return back()->with('success', "Leave type '{$validated['name']}' created successfully.");
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $schoolId = app('current_school_id');

        if ($leaveType->school_id !== $schoolId) abort(403);

        $validated = $request->validate([
            'name'                   => 'required|string|max:100',
            'code'                   => ['required', 'string', 'max:10',
                \Illuminate\Validation\Rule::unique('leave_types')->where('school_id', $schoolId)->ignore($leaveType->id)],
            'days_allowed'           => 'required|integer|min:0|max:365',
            'color'                  => 'required|string|max:7',
            'is_paid'                => 'boolean',
            'carry_forward'          => 'boolean',
            'max_carry_forward_days' => 'integer|min:0',
            'requires_document'      => 'boolean',
            'min_notice_days'        => 'integer|min:0',
            'is_active'              => 'boolean',
            'description'            => 'nullable|string|max:500',
            'sort_order'             => 'integer|min:0',
        ]);

        $validated['code'] = strtoupper($validated['code']);

        $leaveType->update($validated);

        return back()->with('success', "Leave type '{$leaveType->name}' updated.");
    }

    public function destroy(LeaveType $leaveType)
    {
        if ($leaveType->school_id !== app('current_school_id')) abort(403);

        if ($leaveType->leaves()->exists()) {
            return back()->with('error', 'Cannot delete: this leave type has existing leave records. Deactivate it instead.');
        }

        $leaveType->delete();
        return back()->with('success', 'Leave type deleted.');
    }

    public function toggle(LeaveType $leaveType)
    {
        if ($leaveType->school_id !== app('current_school_id')) abort(403);

        $leaveType->update(['is_active' => !$leaveType->is_active]);
        $status = $leaveType->is_active ? 'activated' : 'deactivated';
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
