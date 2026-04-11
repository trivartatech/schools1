<?php

namespace App\Policies;

use App\Models\Leave;
use App\Models\User;

class LeavePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_staff');
    }

    public function view(User $user, Leave $leave): bool
    {
        // Staff can always view their own leave requests.
        if ($leave->user_id === $user->id) {
            return true;
        }

        return $user->hasPermissionTo('view_staff')
            && ($user->school_id === null || $user->school_id === $leave->school_id);
    }

    /** Any authenticated staff member may apply for leave. */
    public function create(User $user): bool
    {
        return true;
    }

    /** Approve / reject / update — requires edit_staff (HR/admin). */
    public function update(User $user, Leave $leave): bool
    {
        return $user->hasPermissionTo('edit_staff')
            && ($user->school_id === null || $user->school_id === $leave->school_id);
    }

    public function delete(User $user, Leave $leave): bool
    {
        // Staff can cancel their own pending leave.
        if ($leave->user_id === $user->id && $leave->status === 'pending') {
            return true;
        }

        return $user->hasPermissionTo('delete_staff')
            && ($user->school_id === null || $user->school_id === $leave->school_id);
    }
}
