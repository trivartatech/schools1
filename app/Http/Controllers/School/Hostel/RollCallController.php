<?php

namespace App\Http\Controllers\School\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\HostelRollCall;
use App\Models\HostelStudent;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RollCallController extends Controller
{
    /**
     * Mark nightly/morning roll call for a hostel.
     */
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $date     = $request->get('date', now()->toDateString());
        $hostelId = $request->get('hostel_id');
        $slot     = $request->get('slot', 'night');

        $hostels = Hostel::where('school_id', $schoolId)->get(['id', 'name', 'type']);

        $students   = collect();
        $attendance = collect();
        $stats      = ['total' => 0, 'present' => 0, 'absent' => 0, 'leave' => 0, 'medical' => 0, 'unmarked' => 0];

        if ($hostelId) {
            // Get all active hostel students in this hostel
            $activeStudentIds = HostelStudent::where('school_id', $schoolId)
                ->where('status', 'Active')
                ->whereHas('bed.room', fn($q) => $q->where('hostel_id', $hostelId))
                ->pluck('student_id');

            $students = Student::whereIn('id', $activeStudentIds)
                ->get(['id', 'first_name', 'last_name', 'admission_no']);

            // Get existing roll call for date/slot
            $attendance = HostelRollCall::where('school_id', $schoolId)
                ->where('hostel_id', $hostelId)
                ->where('date', $date)
                ->where('slot', $slot)
                ->get()
                ->keyBy('student_id');

            $stats['total']   = $students->count();
            $stats['present'] = $attendance->where('status', 'present')->count();
            $stats['absent']  = $attendance->where('status', 'absent')->count();
            $stats['leave']   = $attendance->where('status', 'leave')->count();
            $stats['medical'] = $attendance->where('status', 'medical')->count();
            $stats['unmarked'] = $stats['total'] - $attendance->count();
        }

        return Inertia::render('School/Hostel/RollCall/Index', [
            'hostels'    => $hostels,
            'students'   => $students,
            'attendance' => $attendance,
            'stats'      => $stats,
            'date'       => $date,
            'hostelId'   => (int) $hostelId,
            'slot'       => $slot,
        ]);
    }

    /**
     * Bulk save roll call.
     */
    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'hostel_id' => ['required', Rule::exists('hostels', 'id')->where('school_id', $schoolId)],
            'date'      => 'required|date',
            'slot'      => 'required|in:night,morning',
            'records'   => 'required|array|min:1',
            'records.*.student_id' => 'required|integer',
            'records.*.status'     => 'required|in:present,absent,leave,medical',
            'records.*.remarks'    => 'nullable|string|max:500',
        ]);

        $hostelId = $validated['hostel_id'];
        $date     = $validated['date'];
        $slot     = $validated['slot'];

        // Verify all students belong to this hostel
        $validStudentIds = HostelStudent::where('school_id', $schoolId)
            ->where('status', 'Active')
            ->whereHas('bed.room', fn($q) => $q->where('hostel_id', $hostelId))
            ->pluck('student_id')
            ->toArray();

        DB::transaction(function () use ($validated, $schoolId, $hostelId, $date, $slot, $validStudentIds) {
            foreach ($validated['records'] as $rec) {
                if (!in_array($rec['student_id'], $validStudentIds)) continue;

                HostelRollCall::updateOrCreate(
                    [
                        'school_id'  => $schoolId,
                        'hostel_id'  => $hostelId,
                        'student_id' => $rec['student_id'],
                        'date'       => $date,
                        'slot'       => $slot,
                    ],
                    [
                        'status'    => $rec['status'],
                        'remarks'   => $rec['remarks'] ?? null,
                        'marked_by' => auth()->id(),
                    ]
                );
            }
        });

        return back()->with('success', 'Roll call saved for ' . count($validated['records']) . ' students.');
    }

    /**
     * Monthly roll call report.
     */
    public function report(Request $request)
    {
        $schoolId = app('current_school_id');
        $hostelId = $request->get('hostel_id');
        $month    = $request->integer('month', now()->month);
        $year     = $request->integer('year', now()->year);
        $slot     = $request->get('slot', 'night');

        $hostels = Hostel::where('school_id', $schoolId)->get(['id', 'name']);

        $report      = [];
        $daysInMonth = 0;
        $summary     = ['total_students' => 0, 'avg_present' => 0, 'total_absent' => 0, 'total_medical' => 0];

        if ($hostelId) {
            $startStr    = sprintf('%04d-%02d-01', $year, $month);
            $monthDate   = \Carbon\Carbon::parse($startStr);
            $endStr      = $monthDate->copy()->endOfMonth()->toDateString();
            $daysInMonth = $monthDate->daysInMonth;

            $activeStudentIds = HostelStudent::where('school_id', $schoolId)
                ->where('status', 'Active')
                ->whereHas('bed.room', fn($q) => $q->where('hostel_id', $hostelId))
                ->pluck('student_id');

            $students = Student::whereIn('id', $activeStudentIds)
                ->get(['id', 'first_name', 'last_name', 'admission_no']);

            $records = HostelRollCall::where('school_id', $schoolId)
                ->where('hostel_id', $hostelId)
                ->where('slot', $slot)
                ->where('date', '>=', $startStr)
                ->where('date', '<=', $endStr)
                ->get()
                ->groupBy('student_id');

            $totalPresent = 0;
            $markedDays   = 0;

            foreach ($students as $s) {
                $rows   = $records->get($s->id, collect());
                $days   = [];
                $counts = ['present' => 0, 'absent' => 0, 'leave' => 0, 'medical' => 0, 'working_days' => 0];

                foreach ($rows as $rec) {
                    $day = (int) \Carbon\Carbon::parse($rec->date)->day;
                    $days[$day] = $rec->status;
                    if (isset($counts[$rec->status])) $counts[$rec->status]++;
                    $counts['working_days']++;
                }

                $totalPresent            += $counts['present'];
                $summary['total_absent'] += $counts['absent'];
                $summary['total_medical'] += $counts['medical'];
                $markedDays              += $counts['working_days'];

                $report[] = [
                    'student_id'   => $s->id,
                    'name'         => $s->first_name . ' ' . $s->last_name,
                    'admission_no' => $s->admission_no,
                    'days'         => $days,
                    'counts'       => $counts,
                ];
            }

            $summary['total_students'] = count($report);
            $summary['avg_present'] = $summary['total_students'] > 0 && $markedDays > 0
                ? round($totalPresent / max(1, $markedDays / max(1, $summary['total_students'])))
                : 0;
        }

        return Inertia::render('School/Hostel/RollCall/Report', [
            'hostels'     => $hostels,
            'report'      => $report,
            'daysInMonth' => $daysInMonth,
            'summary'     => $summary,
            'hostelId'    => (int) $hostelId,
            'month'       => $month,
            'year'        => $year,
            'slot'        => $slot,
        ]);
    }
}
