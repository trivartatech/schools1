<?php

namespace App\Console\Commands;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * php artisan roles:sync
 *
 * Re-syncs every user's Spatie role based on their current user_type.
 * Safe to run multiple times; uses syncRoles() so it replaces stale roles.
 *
 * Options:
 *   --school=<id>   Limit to users belonging to a specific school.
 *   --user=<id>     Limit to a single user.
 *   --dry-run       Show what would change without writing anything.
 */
class SyncUserRoles extends Command
{
    protected $signature = 'roles:sync
                            {--school= : Sync only users in this school ID}
                            {--user=   : Sync only this user ID}
                            {--dry-run : Preview changes without making them}';

    protected $description = 'Sync Spatie roles to all users based on their user_type';

    public function handle(): int
    {
        $dryRun   = (bool) $this->option('dry-run');
        $schoolId = $this->option('school');
        $userId   = $this->option('user');

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be written to the database.');
        }

        $query = User::withoutGlobalScopes();

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        if ($userId) {
            $query->where('id', $userId);
        }

        $synced  = 0;
        $skipped = 0;
        $rows    = [];

        $query->chunk(100, function ($users) use ($dryRun, &$synced, &$skipped, &$rows) {
            foreach ($users as $user) {
                $typeEnum = $user->user_type instanceof UserType
                    ? $user->user_type
                    : UserType::tryFrom((string) $user->user_type);

                $roleName = $typeEnum?->toSpatieRole();

                if (!$roleName) {
                    $rows[] = [$user->id, $user->email, (string) $user->user_type, '—', 'SKIP (no mapping)'];
                    $skipped++;
                    continue;
                }

                if (!Role::where('name', $roleName)->where('guard_name', 'web')->exists()) {
                    $rows[] = [$user->id, $user->email, (string) $user->user_type, $roleName, 'SKIP (role not seeded)'];
                    $skipped++;
                    continue;
                }

                $current = $user->roles->pluck('name')->implode(', ') ?: '(none)';
                $rows[]  = [$user->id, $user->email, (string) $user->user_type, $roleName, "{$current} → {$roleName}"];

                if (!$dryRun) {
                    if ($user->school_id) {
                        app(PermissionRegistrar::class)->setPermissionsTeamId($user->school_id);
                    }
                    $user->syncRoles([$roleName]);
                    $synced++;
                }
            }
        });

        $this->table(
            ['User ID', 'Email', 'Type', 'Target Role', 'Action'],
            $rows
        );

        if ($dryRun) {
            $wouldSync = count(array_filter($rows, fn($r) => !str_starts_with($r[4], 'SKIP')));
            $this->info("[DRY RUN] Would sync {$wouldSync} user(s), skip {$skipped}.");
        } else {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->info("✅ Synced {$synced} user(s). Skipped {$skipped}.");
        }

        return self::SUCCESS;
    }
}
