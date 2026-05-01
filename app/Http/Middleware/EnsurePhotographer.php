<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use Closure;
use Illuminate\Http\Request;

/**
 * Gates the /api/mobile/photographer/* endpoints. Allows:
 *   - The synthetic photographer User (user_type = 'photographer')
 *   - Any school admin (so admins can hit these endpoints for testing)
 *
 * Belt-and-braces alongside the Sanctum 'ability:photographer' check on the
 * route group: that ensures the TOKEN can call this endpoint, this ensures
 * the USER is one we actually want here.
 */
class EnsurePhotographer
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        $allowed = $user
            && (
                $user->user_type === UserType::Photographer
                || $user->isAdmin()
                || $user->isSuperAdmin()
            );

        abort_unless($allowed, 403, 'Photographer access only.');

        return $next($request);
    }
}
