<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureYearIsActive
{
    /**
     * Handle an incoming request.
     * Prevents write operations (POST, PUT, PATCH, DELETE) if the selected
     * academic year is not the currently active one (meaning we are in Read-Only Archive mode).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow GET requests (Read-Only)
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        // Allow some exceptions (like switching years or logging out)
        $exceptPaths = [
            'logout',
            'school/switch-academic-year',
            'login',
            // Communication/announcement routes do not depend on academic year —
            // they should work regardless of which year is being viewed.
            'school/communication',
            'school/chat',
            'school/holidays',
            // API routes for mobile app (not year-locked)
            'api/mobile',
        ];

        foreach ($exceptPaths as $path) {
            if ($request->is($path) || $request->is($path . '/*')) {
                return $next($request);
            }
        }

        // Check if the currently bound academic year is active
        $academicYear = app()->bound('current_academic_year') ? app('current_academic_year') : null;

        if ($academicYear && !$academicYear->is_active) {
            // If the request expects JSON (like Inertia form submits or API calls)
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return redirect()->back()->with('error', 'Action denied: You are viewing a past academic year which is in Read-Only Mode.');
            }
            
            abort(403, 'Action denied: The selected academic year is in Read-Only Archive Mode.');
        }

        return $next($request);
    }
}
