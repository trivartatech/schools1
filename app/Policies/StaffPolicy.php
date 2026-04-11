<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_staff');
    }

    public function view(User $user, Staff $staff): bool
    {
        // Any staff member can always view their own profile — no permission required.
        if ($staff->user_id === $user->id) {
            return true;
        }

        return $user->hasPermissionTo('view_staff')
            && ($user->school_id === null || $user->school_id === $staff->school_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_staff');
    }

    public function update(User $user, Staff $staff): bool
    {
        return $user->hasPermissionTo('edit_staff')
            && ($user->school_id === null || $user->school_id === $staff->school_id);
    }

    public function delete(User $user, Staff $staff): bool
    {
        return $user->hasPermissionTo('delete_staff')
            && ($user->school_id === null || $user->school_id === $staff->school_id);
    }

    public function managePayroll(User $user, Staff $staff): bool
    {
        return $user->hasPermissionTo('edit_payroll')
            && ($user->school_id === null || $user->school_id === $staff->school_id);
    }

    public function requestEdit(User $user, Staff $staff): bool
    {
        return $user->hasPermissionTo('request_edit_staff')
            || ($user->hasPermissionTo('edit_staff') && $user->school_id === $staff->school_id);
    }
}
