<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Logs every role attach/detach event to the application log.
 *
 * Listens to:
 *   \Spatie\Permission\Events\RoleAttachedEvent
 *   \Spatie\Permission\Events\RoleDetachedEvent
 *
 * Events are enabled via config/permission.php: 'events_enabled' => true.
 */
class RoleChangeAuditListener
{
    public function handle(object $event): void
    {
        // Only audit User model changes — not other HasRoles models.
        if (!($event->model instanceof User)) {
            return;
        }

        $action   = class_basename($event) === 'RoleAttachedEvent' ? 'assigned' : 'revoked';
        $rolesOrIds = $event->rolesOrIds ?? null;
        if ($rolesOrIds instanceof \Illuminate\Support\Collection) {
            $roleName = $rolesOrIds->pluck('name')->implode(', ');
        } elseif (is_array($rolesOrIds)) {
            $roleName = implode(', ', array_map(fn($r) => is_object($r) ? $r->name : (string) $r, $rolesOrIds));
        } elseif (is_object($rolesOrIds) && isset($rolesOrIds->name)) {
            $roleName = $rolesOrIds->name;
        } else {
            $roleName = (string) ($rolesOrIds ?? 'unknown');
        }

        $performer = auth()->check()
            ? sprintf('%s (#%d)', auth()->user()->email, auth()->id())
            : 'system';

        Log::channel('stack')->info('Role change audit', [
            'action'       => $action,
            'role'         => $roleName,
            'user_id'      => $event->model->id,
            'user_email'   => $event->model->email,
            'performed_by' => $performer,
        ]);
    }
}
