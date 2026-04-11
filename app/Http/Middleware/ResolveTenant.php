<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        $school = null;

        // 1. Header-based (for API / mobile): Trusted ONLY if authenticated
        if (auth()->check()) {
            if ($request->hasHeader('X-School-ID')) {
                $school = School::find($request->header('X-School-ID'));
            } elseif ($request->hasHeader('X-School-Slug')) {
                $school = School::where('slug', $request->header('X-School-Slug'))->first();
            }
            
            // Security: If the resolved school from headers doesn't match the user's school (and user isn't superadmin), allow if user has no assigned school yet (null).
            if ($school && !auth()->user()->isSuperAdmin() 
                && auth()->user()->school_id !== null 
                && (int)auth()->user()->school_id !== (int)$school->id) {
                abort(403, 'Unauthorized school context.');
            }
        }

        // 2. Subdomain-based: school-slug.yourdomain.com
        if (! $school) {
            $host = $request->getHost();
            $parts = explode('.', $host);
            if (count($parts) >= 3) {
                $slug = $parts[0];
                $school = School::where('slug', $slug)->where('status', 'active')->first();
            }
        }

        // ── Fallback: resolve from authenticated user's school ────────────
        // This handles localhost/127.0.0.1 where subdomain detection fails.
        if (! $school && auth()->check()) {
            if (auth()->user()->isSuperAdmin()) {
                if (session()->has('superadmin_school_id')) {
                    $school = \App\Models\School::find(session('superadmin_school_id'));
                }
                if (! $school) {
                    $school = \App\Models\School::where('status', 'active')->first();
                    if ($school) {
                        session(['superadmin_school_id' => $school->id]);
                    }
                }
            } else {
                $school = auth()->user()->school;
            }
        }

        if ($school) {
            App::instance('current_school', $school);
            app()->bind('current_school_id', fn() => $school->id);

            // Globally scope Spatie Permissions to this school (tenant)
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($school->id);

            // Resolve the active Academic Year for this school
            // 1. Check if the user selected a specific year in their session
            $sessionYearId = session('selected_academic_year_id');
            $academicYear = null;

            if ($sessionYearId) {
                $academicYear = \App\Models\AcademicYear::where('school_id', $school->id)
                                ->where('id', $sessionYearId)
                                ->first();
            }

            // 2. If no valid session year, fall back to the default 'is_current' year
            if (! $academicYear) {
                $academicYear = \App\Models\AcademicYear::where('school_id', $school->id)
                                ->where('is_current', true)
                                ->first();
            }

            // 3. Fallback to the latest created year if none is active (edge case)
            if (! $academicYear) {
                $academicYear = \App\Models\AcademicYear::where('school_id', $school->id)
                                ->orderBy('start_date', 'desc')
                                ->first();
            }

            if ($academicYear) {
                App::instance('current_academic_year', $academicYear);
                app()->bind('current_academic_year_id', fn() => $academicYear->id);
            }
        }

        return $next($request);
    }
}

