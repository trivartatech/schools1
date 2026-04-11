<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LeaveController extends Controller
{
    /**
     * Admin: list all leaves with filters
     */
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');

        $query = Leave::where('school_id', $schoolId)
            ->with(['user', 'approver', 'leaveType'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        } elseif ($request->filled('leave_type')) {
            $query->where('leave_type', $request->leave_type);
        }

        $leaves = $query->paginate(20)->withQueryString();

        $leaveTypes = LeaveType::where('school_id', $schoolId)
            ->forStaff()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'code', 'color', 'is_paid', 'days_allowed']);

        return Inertia::render('School/Staff/Leaves/Index', [
            'leaves'     => $leaves,
            'leaveTypes' => $leaveTypes,
            'filters'    => $request->only(['status', 'leave_type', 'leave_type_id']),
        ]);
    }

    /**
     * Staff: submit a leave application
     */
    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('school_id', $schoolId)],
            'start_date'    => 'required|date|after_or_equal:today',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'required|string|max:1000',
        ]);

        // Auto-fill leave_type string from LeaveType name for backward compat
        $leaveType = LeaveType::find($validated['leave_type_id']);

        Leave::create([
            'school_id'     => $schoolId,
            'user_id'       => Auth::id(),
            'leave_type_id' => $validated['leave_type_id'],
            'leave_type'    => strtolower($leaveType->code ?? $leaveType->name ?? 'other'),
            'start_date'    => $validated['start_date'],
            'end_date'      => $validated['end_date'],
            'reason'        => $validated['reason'],
            'status'        => 'pending',
        ]);

        return back()->with('success', 'Leave application submitted successfully.');
    }

    /**
     * Admin: approve a leave
     */
    public function approve(Leave $leave)
    {
        abort_if($leave->school_id !== app('current_school_id'), 403);
        abort_if($leave->status !== 'pending', 422, 'Leave is not in pending state.');

        $leave->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
        ]);

        // Auto-mark staff attendance as 'leave' for each day of the approved leave
        $staff = Staff::where('user_id', $leave->user_id)
            ->where('school_id', $leave->school_id)
            ->first();

        if ($staff) {
            $leave->load('leaveType');
            $remark = 'Approved leave: ' . ($leave->leaveType->name ?? $leave->leave_type);
            $start = Carbon::parse($leave->start_date);
            $end = Carbon::parse($leave->end_date);

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                StaffAttendance::updateOrCreate(
                    [
                        'school_id' => $leave->school_id,
                        'staff_id'  => $staff->id,
                        'date'      => $date->toDateString(),
                    ],
                    [
                        'status'    => 'leave',
                        'remarks'   => $remark,
                        'marked_by' => Auth::id(),
                    ]
                );
            }
        }

        return back()->with('success', 'Leave approved and attendance updated.');
    }

    /**
     * Admin: reject a leave
     */
    public function reject(Request $request, Leave $leave)
    {
        abort_if($leave->school_id !== app('current_school_id'), 403);
        abort_if($leave->status !== 'pending', 422, 'Leave is not in pending state.');

        $leave->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
        ]);

        return back()->with('success', 'Leave rejected.');
    }

    /**
     * Admin: revert a leave to pending
     */
    public function revert(Request $request, Leave $leave)
    {
        abort_if($leave->school_id !== app('current_school_id'), 403);
        abort_if(!in_array($leave->status, ['approved', 'rejected']), 422, 'Only approved or rejected leaves can be reverted.');

        $wasApproved = $leave->status === 'approved';

        $leave->update([
            'status' => 'pending',
            'approved_by' => null,
        ]);

        // If was approved, remove auto-created 'leave' attendance records
        if ($wasApproved) {
            $staff = Staff::where('user_id', $leave->user_id)
                ->where('school_id', $leave->school_id)
                ->first();

            if ($staff) {
                StaffAttendance::where('school_id', $leave->school_id)
                    ->where('staff_id', $staff->id)
                    ->where('status', 'leave')
                    ->whereDate('date', '>=', $leave->start_date)
                    ->whereDate('date', '<=', $leave->end_date)
                    ->delete();
            }
        }

        return back()->with('success', 'Leave reverted to pending status.');
    }
}
