<?php

namespace App\Policies;

use App\Models\FeeGroup;
use App\Models\User;

class FeeGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_fee');
    }

    public function view(User $user, FeeGroup $feeGroup): bool
    {
        return $user->hasPermissionTo('view_fee')
            && ($user->school_id === null || $user->school_id === $feeGroup->school_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_fee');
    }

    public function update(User $user, FeeGroup $feeGroup): bool
    {
        return $user->hasPermissionTo('edit_fee')
            && ($user->school_id === null || $user->school_id === $feeGroup->school_id);
    }

    public function delete(User $user, FeeGroup $feeGroup): bool
    {
        return $user->hasPermissionTo('delete_fee')
            && ($user->school_id === null || $user->school_id === $feeGroup->school_id);
    }
}
