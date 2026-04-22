<?php

namespace App\Http\Controllers\School\FrontOffice;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CallLogController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school_id');

        $query = CallLog::where('school_id', $schoolId)
                    ->with(['handledBy.user', 'relatedStudent']);

        if ($yearId = session('selected_academic_year_id')) {
            $query->where('academic_year_id', $yearId);
        }

        return Inertia::render('School/FrontOffice/CallLogs/Index', [
            'callLogs' => $query->latest()->get(),
            'staffs' => Staff::where('school_id', $schoolId)->with('user')->get(),
            'students' => Student::where('school_id', $schoolId)->enrolledInCurrentYear()->get(['id', 'first_name', 'last_name', 'admission_no'])
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'caller_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:25',
            'call_type' => 'required|in:Incoming,Outgoing',
            'purpose' => 'required|in:Enquiry,Complaint,Follow-up,Admission,Other',
            'handled_by_id' => ['nullable', Rule::exists('staff', 'id')->where('school_id', $schoolId)],
            'related_student_id' => ['nullable', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'notes' => 'nullable|string',
            'follow_up_date' => 'nullable|date',
        ]);

        $callLog = new CallLog($validated);
        $callLog->school_id = $schoolId;
        $callLog->academic_year_id = session('selected_academic_year_id');
        $callLog->save();

        return back()->with('success', 'Call log recorded successfully.');
    }

    public function update(Request $request, CallLog $callLog)
    {
        abort_if($callLog->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'follow_up_completed' => 'required|boolean',
        ]);

        $callLog->update($validated);

        return back()->with('success', 'Follow-up status updated.');
    }

    public function destroy(CallLog $callLog)
    {
        abort_if($callLog->school_id !== app('current_school_id'), 403);

        $callLog->delete();
        return back()->with('success', 'Call log removed.');
    }

    /**
     * Follow-up dashboard — overdue, today, upcoming follow-ups.
     */
    public function followUps()
    {
        $schoolId = app('current_school_id');
        $today = now()->toDateString();

        $base = CallLog::where('school_id', $schoolId)
            ->whereNotNull('follow_up_date')
            ->with(['handledBy.user', 'relatedStudent']);

        $overdue = (clone $base)->where('follow_up_completed', false)
            ->where('follow_up_date', '<', $today)
            ->orderBy('follow_up_date')
            ->get();

        $todayList = (clone $base)->where('follow_up_completed', false)
            ->where('follow_up_date', $today)
            ->orderBy('follow_up_date')
            ->get();

        $upcoming = (clone $base)->where('follow_up_completed', false)
            ->where('follow_up_date', '>', $today)
            ->orderBy('follow_up_date')
            ->take(20)
            ->get();

        $recentCompleted = (clone $base)->where('follow_up_completed', true)
            ->latest('updated_at')
            ->take(10)
            ->get();

        return Inertia::render('School/FrontOffice/CallLogs/FollowUps', [
            'overdue'         => $overdue,
            'today'           => $todayList,
            'upcoming'        => $upcoming,
            'recentCompleted' => $recentCompleted,
            'stats' => [
                'overdue_count'  => $overdue->count(),
                'today_count'    => $todayList->count(),
                'upcoming_count' => $upcoming->count(),
            ],
        ]);
    }
}
