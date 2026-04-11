<?php

namespace App\Policies;

use App\Models\Hostel;
use App\Models\User;

class HostelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_hostel');
    }

    public function view(User $user, Hostel $hostel): bool
    {
        return $user->hasPermissionTo('view_hostel')
            && ($user->school_id === null || $user->school_id === $hostel->school_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_hostel');
    }

    public function update(User $user, Hostel $hostel): bool
    {
        return $user->hasPermissionTo('edit_hostel')
            && ($user->school_id === null || $user->school_id === $hostel->school_id);
    }

    public function delete(User $user, Hostel $hostel): bool
    {
        return $user->hasPermissionTo('delete_hostel')
            && ($user->school_id === null || $user->school_id === $hostel->school_id);
    }
}
