<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_attendance');
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if (!$user->hasPermissionTo('view_attendance') && !$user->hasPermissionTo('view_own_attendance')) {
            return false;
        }

        // Tenant isolation
        if ($user->school_id && $user->school_id !== $attendance->school_id) {
            return false;
        }

        // Students may only view their own attendance
        if ($user->isStudent()) {
            return $attendance->student_id === $user->student?->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_attendance');
    }

    /**
     * General update gate — edit_attendance covers same-day corrections.
     * Historical corrections (past dates) additionally require edit_past_attendance.
     */
    public function update(User $user, Attendance $attendance): bool
    {
        if (!$user->hasPermissionTo('edit_attendance')) {
            return false;
        }

        if ($user->school_id && $user->school_id !== $attendance->school_id) {
            return false;
        }

        // If the attendance date is in the past (not today), require explicit permission
        $attendanceDate = $attendance->date ?? $attendance->attendance_date ?? null;
        if ($attendanceDate && $attendanceDate < now()->toDateString()) {
            return $user->hasPermissionTo('edit_past_attendance');
        }

        return true;
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->hasPermissionTo('delete_attendance')
            && ($user->school_id === null || $user->school_id === $attendance->school_id);
    }

    /** Export attendance report to Excel/PDF */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('export_attendance')
            || $user->hasPermissionTo('view_attendance');
    }
}
