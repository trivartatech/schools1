<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_communication');
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->hasPermissionTo('view_communication')
            && ($user->school_id === null || $user->school_id === $announcement->school_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_communication');
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->hasPermissionTo('edit_communication')
            && ($user->school_id === null || $user->school_id === $announcement->school_id);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->hasPermissionTo('delete_communication')
            && ($user->school_id === null || $user->school_id === $announcement->school_id);
    }
}
