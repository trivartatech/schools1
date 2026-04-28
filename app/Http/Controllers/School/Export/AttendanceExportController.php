<?php

namespace App\Http\Controllers\School\Export;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CourseClass;
use App\Models\Section;
use App\Models\StudentAcademicHistory;
use App\Traits\Exportable;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceExportController extends Controller
{
    use Exportable;

    public function __invoke(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $classId   = $request->input('class_id');
        $sectionId = $request->input('section_id');
        $month     = $request->input('month', now()->format('Y-m'));

        if (!$classId || !$academicYearId) {
            return back()->withErrors(['error' => 'Class and academic year are required for export.']);
        }

        $startDate   = Carbon::parse($month . '-01');
        $endDate     = $startDate->copy()->endOfMonth();
        $daysInMonth = $startDate->daysInMonth;

        $historyQuery = StudentAcademicHistory::with('student')
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->where('status', 'current');

        if ($sectionId) {
            $historyQuery->where('section_id', $sectionId);
        }

        $histories = $historyQuery->orderBy('roll_no')->get();

        $attendanceQuery = Attendance::where('school_id', $schoolId)
            ->where('class_id', $classId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNull('subject_id');

        if ($sectionId) {
            $attendanceQuery->where('section_id', $sectionId);
        }

        $attendance = $attendanceQuery->get()->groupBy('student_id');

        // Headers: S.No, Name, Roll No, day numbers 1..N, then summary
        $headers = ['S.No', 'Name', 'Roll No'];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $headers[] = (string) $d;
        }
        $headers = array_merge($headers, ['Present', 'Absent', 'Late', 'Leave', '%']);

        $rows = [];
        foreach ($histories as $i => $h) {
            $student = $h->student;
            $studentAttendance = $attendance->get($student->id, collect());
            $byDate = $studentAttendance->keyBy(fn($a) => Carbon::parse($a->date)->day);

            $row = [$i + 1, $student->first_name . ' ' . $student->last_name, $h->roll_no ?? $student->roll_no];

            $present = 0; $absent = 0; $late = 0; $leave = 0;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $record = $byDate->get($d);
                if ($record) {
                    $status = $record->status;
                    $row[] = strtoupper(substr($status, 0, 1));
                    match ($status) {
                        'present'  => $present++,
                        'absent'   => $absent++,
                        'late'     => $late++,
                        'half_day' => $present++,
                        'leave'    => $leave++,
                        default    => null,
                    };
                } else {
                    $row[] = '-';
                }
            }

            $total = $present + $absent + $late + $leave;
            // Canonical formula: (present + late*0.5 + half_day*0.5) / total
            // Note: this exporter doesn't track half_day separately yet — late = half here.
            $pct   = $total > 0 ? round(($present + $late * 0.5) / $total * 100) : 0;
            $row   = array_merge($row, [$present, $absent, $late, $leave, $pct . '%']);
            $rows[] = $row;
        }

        $className   = CourseClass::find($classId)?->name ?? '';
        $sectionName = $sectionId ? Section::find($sectionId)?->name ?? '' : 'all';
        $filename    = "attendance-{$className}-{$sectionName}-{$month}";

        return $this->exportResponse($request, $headers, $rows, $filename, [
            'orientation' => 'landscape',
        ]);
    }
}
