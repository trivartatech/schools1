<?php

namespace App\Policies;

use App\Models\Payroll;
use App\Models\User;

class PayrollPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_payroll');
    }

    public function view(User $user, Payroll $payroll): bool
    {
        // Staff members can view their own payslip.
        if ($payroll->staff?->user_id === $user->id) {
            return true;
        }

        return $user->hasPermissionTo('view_payroll')
            && ($user->school_id === null || $user->school_id === $payroll->school_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_payroll');
    }

    public function update(User $user, Payroll $payroll): bool
    {
        return $user->hasPermissionTo('edit_payroll')
            && ($user->school_id === null || $user->school_id === $payroll->school_id);
    }

    public function delete(User $user, Payroll $payroll): bool
    {
        return $user->hasPermissionTo('delete_payroll')
            && ($user->school_id === null || $user->school_id === $payroll->school_id);
    }

    /** Execute/disburse a payroll run. */
    public function execute(User $user, Payroll $payroll): bool
    {
        return $user->hasPermissionTo('edit_payroll')
            && ($user->school_id === null || $user->school_id === $payroll->school_id);
    }
}
