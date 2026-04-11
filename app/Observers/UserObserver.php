<?php

namespace App\Observers;

use App\Enums\UserType;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Keeps the Spatie role in sync with the user_type field.
 *
 * Whenever a User is created or their user_type changes, we automatically
 * assign the corresponding Spatie role so permission checks work immediately —
 * without needing to re-run the RolePermissionSeeder.
 *
 * Also clears the Spatie permission cache after every role change so the new
 * role takes effect on the next request rather than waiting for the 24-hour TTL.
 */
class UserObserver
{
    public function created(User $user): void
    {
        $this->syncRole($user);
    }

    public function updated(User $user): void
    {
        // Only re-sync when user_type actually changed to avoid unnecessary writes.
        if ($user->wasChanged('user_type')) {
            $this->syncRole($user);
        }
    }

    private function syncRole(User $user): void
    {
        // Delegate to the single source of truth: UserType::toSpatieRole()
        $typeEnum = $user->user_type instanceof UserType
            ? $user->user_type
            : UserType::tryFrom((string) $user->user_type);

        $roleName = $typeEnum?->toSpatieRole();

        if (!$roleName) {
            return;
        }

        // Guard: only assign if the role has been seeded into the database.
        if (!Role::where('name', $roleName)->where('guard_name', 'web')->exists()) {
            return;
        }

        // Scope to the user's school when Spatie teams are enabled.
        if ($user->school_id) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->school_id);
        }

        $user->syncRoles([$roleName]);

        // Flush the Spatie permission cache so the new role is visible immediately.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
