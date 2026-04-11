<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ImpersonationController;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     * These are available in every Vue component as $page.props.
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $school = app()->bound('current_school') ? app('current_school') : null;

        // ── Impersonation auto-expiry ────────────────────────────────────
        $impersonation = null;
        $originalUserId = $request->session()->get(ImpersonationController::sessionKey());
        if ($originalUserId && $user) {
            $startedAt = $request->session()->get(ImpersonationController::sessionStartedAtKey());
            $elapsed = $startedAt ? (time() - (int) $startedAt) : 0;

            if ($elapsed >= ImpersonationController::timeoutSeconds()) {
                // Auto-expire: restore admin and clear session
                $logId = $request->session()->get('impersonation.log_id');
                if ($logId) {
                    \App\Models\ImpersonationLog::where('id', $logId)->update(['ended_at' => now()]);
                }
                $original = User::find($originalUserId);
                $request->session()->forget([
                    ImpersonationController::sessionKey(),
                    'impersonation.log_id',
                    ImpersonationController::sessionNameKey(),
                    ImpersonationController::sessionStartedAtKey(),
                ]);
                if ($original) {
                    Auth::login($original);
                    $request->session()->regenerate();
                }
                // Impersonation expired — no banner needed
            } else {
                $originalUser = User::find($originalUserId);
                $impersonation = [
                    'active'            => true,
                    'impersonated_name' => $request->session()->get(ImpersonationController::sessionNameKey()),
                    'original_name'     => $originalUser?->name,
                    'original_user_type'=> $originalUser?->user_type,
                    'elapsed_seconds'   => $elapsed,
                    'timeout_seconds'   => ImpersonationController::timeoutSeconds(),
                ];
            }
        }
        // ────────────────────────────────────────────────────────────────

        // ── Notifications ────────────────────────────────────────────────
        // once() memoizes for the lifetime of this request so Inertia partial
        // reloads and chat-polling requests (throttle:120,1) don't hammer the DB.
        $unreadCount = $user ? once(fn () => $user->unreadNotifications()->count()) : 0;
        $notifications = $user ? once(fn () => $user->unreadNotifications()
            ->limit(5)->get()->map(fn($n) => [
                'id'         => $n->id,
                'data'       => $n->data,
                'created_at' => $n->created_at->diffForHumans(),
            ])) : [];
        // ────────────────────────────────────────────────────────────────

        $permissions = [];
        $roles = [];
        if ($user) {
            $schoolId = app()->bound('current_school_id') ? app('current_school_id') : $user->school_id;
            $registrar = app(\Spatie\Permission\PermissionRegistrar::class);

            $permissions = once(function() use ($user, $registrar, $schoolId) {
                $registrar->setPermissionsTeamId(null);
                $global = $user->getAllPermissions()->pluck('name');

                $scoped = collect();
                if ($schoolId) {
                    $user->unsetRelation('permissions')->unsetRelation('roles');
                    $registrar->setPermissionsTeamId($schoolId);
                    $scoped = $user->getAllPermissions()->pluck('name');
                }
                return $global->merge($scoped)->unique()->values();
            });

            $roles = once(function() use ($user, $registrar, $schoolId) {
                $registrar->setPermissionsTeamId(null);
                $globalRoles = $user->getRoleNames();

                $scopedRoles = collect();
                if ($schoolId) {
                    $user->unsetRelation('permissions')->unsetRelation('roles');
                    $registrar->setPermissionsTeamId($schoolId);
                    $scopedRoles = $user->getRoleNames();
                }
                return $globalRoles->merge($scopedRoles)->unique()->values();
            });

            // Ensure consistent final state for the rest of the request
            $registrar->setPermissionsTeamId($schoolId);
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'email'     => $user->email,
                    'avatar'    => $user->avatar,
                    'user_type' => $user->user_type,
                    'school_id' => $user->school_id,
                ] : null,
                'notifications'              => $notifications,
                'unread_notifications_count' => $unreadCount,
                'permissions'                => $permissions,
                'roles'                      => $roles,
            ],
            'school' => $school ? [
                'id'              => $school->id,
                'name'            => $school->name,
                'logo'            => $school->logo,
                'board'           => $school->board,
                'currency'        => $school->currency ?? '₹',
                'settings'        => $school->settings ?? [],
                'features'        => $school->features ?? [],
                'geo_fence_lat'   => $school->geo_fence_lat,
                'geo_fence_lng'   => $school->geo_fence_lng,
                'geo_fence_radius'=> $school->geo_fence_radius,
            ] : null,
            'academic_year' => app()->bound('current_academic_year') ? [
                'id'         => app('current_academic_year')->id,
                'name'       => app('current_academic_year')->name,
                'start_date' => app('current_academic_year')->start_date,
                'end_date'   => app('current_academic_year')->end_date,
                'is_active'  => app('current_academic_year')->status === 'active',
            ] : null,
            'all_academic_years' => $school ? once(fn () => \App\Models\AcademicYear::where('school_id', $school->id)
                ->orderBy('start_date', 'desc')->get()->map(fn($y) => [
                    'id'        => $y->id,
                    'name'      => $y->name,
                    'is_active' => $y->status === 'active',
                ])) : [],
            'all_schools' => ($user && $user->user_type === 'super_admin') ? once(fn () =>
                \App\Models\School::where('status', 'active')->get()->map(fn($s) => [
                    'id'   => $s->id,
                    'name' => $s->name,
                ])) : [],
            'punch' => $user && $school ? once(function () use ($user, $school) {
                $staff = Staff::where('user_id', $user->id)->where('school_id', $school->id)->first();
                if (! $staff) return null;

                $today = now()->toDateString();
                $att = StaffAttendance::where('school_id', $school->id)
                    ->where('staff_id', $staff->id)
                    ->whereDate('date', $today)
                    ->first();

                // Last 7 days
                $history = StaffAttendance::where('school_id', $school->id)
                    ->where('staff_id', $staff->id)
                    ->orderByDesc('date')
                    ->limit(7)
                    ->get()
                    ->map(fn ($r) => [
                        'date'      => $r->date->format('Y-m-d'),
                        'day'       => $r->date->format('D'),
                        'status'    => $r->status,
                        'check_in'  => $r->check_in,
                        'check_out' => $r->check_out,
                    ]);

                return [
                    'staff_id'    => $staff->id,
                    'employee_id' => $staff->employee_id,
                    'attendance'  => $att ? [
                        'status'    => $att->status,
                        'check_in'  => $att->check_in,
                        'check_out' => $att->check_out,
                    ] : null,
                    'history'  => $history,
                    'geoFence' => [
                        'lat'     => (float) $school->geo_fence_lat,
                        'lng'     => (float) $school->geo_fence_lng,
                        'radius'  => (int) $school->geo_fence_radius,
                        'enabled' => (bool) ($school->geo_fence_lat && $school->geo_fence_lng),
                    ],
                ];
            }) : null,
            // Flash — resolved eagerly and memoised per-request so that
            // partial reloads (notifications/chat polling) don't keep
            // returning the same success message repeatedly. `once()`
            // makes the closure return the same value for the lifetime
            // of this request; Laravel's StartSession middleware ages
            // the flash bag on the NEXT request as usual.
            'flash' => once(fn () => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'status'  => $request->session()->get('status'),
            ]),
            'impersonation' => $impersonation,
        ]);
    }
}
