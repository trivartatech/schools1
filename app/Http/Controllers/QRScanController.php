<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TeacherScopeService;

class QRScanController extends Controller
{
    /**
     * Show student profile from QR code, and allow staff to mark attendance
     */
    public function show($uuid)
    {
        $schoolId = app()->bound('current_school_id') ? app('current_school_id') : null;

        $student = \App\Models\Student::with(['school', 'currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->where('uuid', $uuid)
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->firstOrFail();

        // Optional: Check if the user is logged in and is staff
        $isStaff = auth()->check() && $schoolId && (auth()->user()->isTeacher() || auth()->user()->isAdmin());

        // Check if attendance is already marked for today
        $attendanceToday = null;
        if ($isStaff) {
            $attendanceToday = \App\Models\Attendance::where('student_id', $student->id)
                ->where('date', now()->toDateString())
                ->first();
        }

        return \Inertia\Inertia::render('Public/StudentProfileQR', [
            'student' => $student,
            'isStaff' => $isStaff,
            'attendanceToday' => $attendanceToday,
        ]);
    }

    /**
     * Mark attendance from the QR Scan page
     */
    public function markAttendance(Request $request, $uuid)
    {
        // Must be teacher or school admin (including principal)
        if (!auth()->check() || !(auth()->user()->isTeacher() || auth()->user()->isAdmin())) {
            abort(403, 'Unauthorized. Staff only.');
        }

        $request->validate([
            'status' => 'required|in:present,absent,late,half_day,leave'
        ]);

        $schoolId = app('current_school_id');

        // Scope student to current school to prevent cross-school IDOR
        $student = \App\Models\Student::with('currentAcademicHistory')
            ->where('uuid', $uuid)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $academicYearId = app('current_academic_year_id');
        $history = $student->currentAcademicHistory;

        if (!$history) {
            return back()->with('error', 'Student does not have an active academic record.');
        }

        // Teachers can only mark attendance for students in their assigned sections
        if (auth()->user()->isTeacher()) {
            $scope = app(TeacherScopeService::class)->for(auth()->user());
            if ($scope->sectionIds->isNotEmpty() && !$scope->sectionIds->contains($history->section_id)) {
                abort(403, 'You are not assigned to this student\'s section.');
            }
        }

        // Auto-promote a "Present" tap to "Late" once the configured student
        // late threshold has passed. Configured at /school/settings/attendance-timings.
        // Other explicit picks (absent / late / half_day / leave) pass through.
        $school = \App\Models\School::find($schoolId);
        $status = $school?->resolveStudentAttendanceStatus($request->status) ?? $request->status;

        \App\Models\Attendance::updateOrCreate(
            [
                'school_id'  => $schoolId,
                'student_id' => $student->id,
                'date'       => now()->toDateString(),
            ],
            [
                'academic_year_id' => $academicYearId,
                'class_id'         => $history->class_id,
                'section_id'       => $history->section_id,
                'status'           => $status,
                'marked_by'        => auth()->id(),
            ]
        );

        $promoted = $request->status === 'present' && $status === 'late';
        $message = $promoted
            ? 'Marked late (after ' . $school->lateThresholdFor('student') . ').'
            : 'Attendance marked successfully.';

        return back()->with('success', $message);
    }
}
