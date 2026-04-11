<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class StaffAttendanceController extends Controller
{
    /**
     * Mark attendance page — shows all active staff for a given date.
     */
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $date = $request->input('date', now()->toDateString());

        $staff = Staff::where('school_id', $schoolId)
            ->whereIn('status', ['active', 'on_leave'])
            ->with(['user', 'department', 'designation'])
            ->orderBy('employee_id')
            ->get();

        $existing = StaffAttendance::where('school_id', $schoolId)
            ->whereDate('date', $date)
            ->get()
            ->keyBy('staff_id');

        // Find staff with approved leaves on this date
        $approvedLeaves = Leave::where('school_id', $schoolId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->with('leaveType')
            ->get();

        $onLeaveMap = [];
        foreach ($approvedLeaves as $leave) {
            $staffRecord = $staff->firstWhere('user_id', $leave->user_id);
            if ($staffRecord) {
                $onLeaveMap[$staffRecord->id] = $leave->leaveType->name ?? $leave->leave_type;
            }
        }

        $attendance = $staff->map(fn ($s) => [
            'staff_id'        => $s->id,
            'name'            => $s->user->name ?? 'Unknown',
            'employee_id'     => $s->employee_id,
            'department'      => $s->department?->name,
            'designation'     => $s->designation?->name,
            'photo'           => $s->photo_url,
            'status'          => $existing[$s->id]->status ?? ($onLeaveMap[$s->id] ?? false ? 'leave' : null),
            'check_in'        => $existing[$s->id]->check_in ?? null,
            'check_out'       => $existing[$s->id]->check_out ?? null,
            'remarks'         => $existing[$s->id]->remarks ?? null,
            'on_leave'        => isset($onLeaveMap[$s->id]),
            'leave_type_name' => $onLeaveMap[$s->id] ?? null,
        ]);

        return Inertia::render('School/Staff/Attendance/Index', [
            'attendance' => $attendance,
            'date'       => $date,
            'stats'      => [
                'total'   => $staff->count(),
                'present' => $existing->whereIn('status', ['present', 'late'])->count(),
                'absent'  => $existing->where('status', 'absent')->count(),
                'leave'   => $existing->where('status', 'leave')->count(),
                'half_day'=> $existing->where('status', 'half_day')->count(),
                'unmarked'=> $staff->count() - $existing->count(),
            ],
        ]);
    }

    /**
     * Bulk save attendance for a date.
     */
    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $request->validate([
            'date'                  => 'required|date',
            'records'               => 'required|array|min:1',
            'records.*.staff_id'    => 'required|integer',
            'records.*.status'      => 'required|in:present,absent,late,half_day,leave,holiday',
            'records.*.check_in'    => 'nullable|date_format:H:i',
            'records.*.check_out'   => 'nullable|date_format:H:i',
            'records.*.remarks'     => 'nullable|string|max:255',
        ]);

        $date = $request->date;
        $markedBy = auth()->id();

        // Collect staff IDs that have approved leave on this date (cannot override)
        $approvedLeaveUserIds = Leave::where('school_id', $schoolId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->pluck('user_id');

        $staffOnLeaveIds = $approvedLeaveUserIds->isNotEmpty()
            ? Staff::where('school_id', $schoolId)
                ->whereIn('user_id', $approvedLeaveUserIds)
                ->pluck('id')
            : collect();

        DB::transaction(function () use ($request, $schoolId, $date, $markedBy, $staffOnLeaveIds) {
            foreach ($request->records as $row) {
                // Verify staff belongs to this school
                $staffExists = Staff::where('id', $row['staff_id'])
                    ->where('school_id', $schoolId)
                    ->exists();

                if (!$staffExists) continue;

                // Force 'leave' status for staff with approved leave
                $status = $staffOnLeaveIds->contains($row['staff_id'])
                    ? 'leave'
                    : $row['status'];

                StaffAttendance::updateOrCreate(
                    [
                        'school_id' => $schoolId,
                        'staff_id'  => $row['staff_id'],
                        'date'      => $date,
                    ],
                    [
                        'status'    => $status,
                        'check_in'  => $row['check_in'] ?? null,
                        'check_out' => $row['check_out'] ?? null,
                        'remarks'   => $row['remarks'] ?? null,
                        'marked_by' => $markedBy,
                    ]
                );
            }
        });

        return back()->with('success', 'Staff attendance saved successfully.');
    }

    /**
     * Monthly attendance report.
     */
    public function report(Request $request)
    {
        $schoolId = app('current_school_id');
        $month = $request->integer('month', now()->month);
        $year  = $request->integer('year', now()->year);

        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate   = $startDate->copy()->endOfMonth();
        $daysInMonth = $startDate->daysInMonth;

        $staff = Staff::where('school_id', $schoolId)
            ->whereIn('status', ['active', 'on_leave'])
            ->with(['user', 'department'])
            ->orderBy('employee_id')
            ->get();

        $records = StaffAttendance::where('school_id', $schoolId)
            ->whereDate('date', '>=', $startDate->toDateString())
            ->whereDate('date', '<=', $endDate->toDateString())
            ->get()
            ->groupBy('staff_id');

        $report = $staff->map(function ($s) use ($records, $daysInMonth) {
            $staffRecords = $records->get($s->id, collect());
            $byDate = $staffRecords->keyBy(fn ($r) => $r->date->format('Y-m-d'));

            $counts = [
                'present'  => $staffRecords->whereIn('status', ['present', 'late'])->count(),
                'absent'   => $staffRecords->where('status', 'absent')->count(),
                'late'     => $staffRecords->where('status', 'late')->count(),
                'half_day' => $staffRecords->where('status', 'half_day')->count(),
                'leave'    => $staffRecords->where('status', 'leave')->count(),
                'holiday'  => $staffRecords->where('status', 'holiday')->count(),
            ];
            $counts['working_days'] = $daysInMonth - $counts['holiday'];
            $counts['unmarked'] = $daysInMonth - $staffRecords->count();

            // Build day-by-day map for calendar view
            $days = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr = sprintf('%04d-%02d-%02d', $byDate->first()?->date->year ?? now()->year, $byDate->first()?->date->month ?? now()->month, $d);
                $rec = $byDate->get($dateStr);
                $days[$d] = $rec ? $rec->status : null;
            }

            return [
                'staff_id'    => $s->id,
                'name'        => $s->user->name ?? 'Unknown',
                'employee_id' => $s->employee_id,
                'department'  => $s->department?->name,
                'counts'      => $counts,
                'days'        => $days,
            ];
        });

        // Overall summary
        $summary = [
            'total_staff'    => $staff->count(),
            'avg_present'    => $staff->count() > 0 ? round($report->avg('counts.present'), 1) : 0,
            'total_absent'   => $report->sum('counts.absent'),
            'total_late'     => $report->sum('counts.late'),
        ];

        return Inertia::render('School/Staff/Attendance/Report', [
            'report'  => $report->values(),
            'month'   => $month,
            'year'    => $year,
            'daysInMonth' => $daysInMonth,
            'summary' => $summary,
        ]);
    }
}
