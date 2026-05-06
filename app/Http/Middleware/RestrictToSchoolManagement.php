<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RestrictToSchoolManagement
 *
 * Gate middleware that restricts routes to users who are allowed to
 * manage school administration. Any user_type NOT in the $allowed list
 * will receive a 403 (or redirect, for Inertia).
 *
 * Usage in routes:
 *   Route::middleware('school.management')->group(...)            → all staff types
 *   Route::middleware('school.management:admin_only')->group(...) → admin/principal only
 */
class RestrictToSchoolManagement
{
    /**
     * user_types allowed at management level.
     * With the transition to a purely permission-based architecture, any staff
     * can pass the base route gate, while Spatie controller checks enforce specifics.
     */
    private const MANAGEMENT_TYPES = [
        'admin', 'super_admin', 'school_admin', 'principal',
        'teacher', 'accountant', 'driver', 'front_gate_keeper',
    ];

    /** user_types with full school admin capabilities. */
    private const ADMIN_ONLY_TYPES = [
        'admin', 'super_admin', 'school_admin', 'principal',
    ];

    public function handle(Request $request, Closure $next, string $level = 'management'): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowed = $level === 'admin_only'
            ? self::ADMIN_ONLY_TYPES
            : self::MANAGEMENT_TYPES;

        if (! in_array($user->user_type->value, $allowed, true)) {
            // If the user isn't a standard management type but has been explicitly 
            // granted Spatie permissions, allow them to pass the base gate.
            if ($user->getAllPermissions()->isEmpty()) {
                if ($request->header('X-Inertia')) {
                    // Inertia requires a redirect response — never raw JSON.
                    // Redirect back (or to dashboard) with a flash error the layout's toast will display.
                    $fallback = $request->headers->get('referer') ? back() : redirect('/dashboard');
                    return $fallback->with('error', 'You do not have permission to access this area.');
                }
                abort(403, 'You do not have permission to access this area.');
            }
        }

        return $next($request);
    }
}
