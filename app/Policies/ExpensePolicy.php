<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_expense');
    }

    public function view(User $user, Expense $expense): bool
    {
        if (!$user->hasPermissionTo('view_expense')) {
            return false;
        }

        // Tenant isolation
        return $user->school_id === null || $user->school_id === $expense->school_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_expense');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->hasPermissionTo('edit_expense')
            && ($user->school_id === null || $user->school_id === $expense->school_id);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->hasPermissionTo('delete_expense')
            && ($user->school_id === null || $user->school_id === $expense->school_id);
    }

    /** Manually post an expense to the General Ledger */
    public function postGl(User $user, Expense $expense): bool
    {
        return $user->hasPermissionTo('edit_expense')
            && ($user->school_id === null || $user->school_id === $expense->school_id);
    }
}
