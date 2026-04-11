<?php

namespace App\Http\Controllers;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ImpersonationController extends Controller
{
    /**
     * Role hierarchy: higher index = more powerful role.
     * A user can only impersonate roles that appear BELOW them in this list.
     */
    private const ROLE_HIERARCHY = [
        'super_admin'  => 100,
        'admin'        => 80,
        'school_admin' => 70,
        'principal'    => 60,
        'accountant'   => 40,
        'teacher'      => 30,
        'driver'       => 20,
        'parent'       => 10,
        'student'      => 5,
    ];

    /**
     * Roles that are allowed to initiate impersonation.
     */
    private const ALLOWED_IMPERSONATORS = ['super_admin', 'admin', 'school_admin', 'principal'];

    // ── Session Keys ────────────────────────────────────────────────────
    private const KEY_IMPERSONATOR         = 'impersonation.original_user_id';
    private const KEY_LOG_ID               = 'impersonation.log_id';
    private const KEY_IMPERSONATED_NAME    = 'impersonation.impersonated_name';
    private const TIMEOUT_SECONDS          = 3600; // 1 hour
    private const KEY_STARTED_AT           = 'impersonation.started_at';

    // ────────────────────────────────────────────────────────────────────

    /**
     * List all users for impersonation management (admin/super-admin view).
     */
    public function index(Request $request)
    {
        $actor = $request->user();

        abort_unless($this->canImpersonate($actor), 403, 'Unauthorized');

        $actorLevel = self::ROLE_HIERARCHY[$actor->user_type] ?? 0;

        // Build allowed user_type list (only roles strictly below the actor)
        $allowedTypes = array_keys(array_filter(
            self::ROLE_HIERARCHY,
            fn($lvl) => $lvl < $actorLevel
        ));

        $users = User::with(['studentParent'])
            ->withTrashed(false)
            ->when($actor->user_type !== 'super_admin', fn($q) =>
                $q->where('school_id', $actor->school_id)
            )
            ->whereIn('user_type', $allowedTypes)
            ->orderBy('user_type')
            ->orderBy('name')
            ->get()
            ->map(function ($u) {
                $displayName = $u->name;
                if ($u->user_type === 'parent' && $u->studentParent) {
                    $displayName = $u->studentParent->father_name 
                        ?: ($u->studentParent->mother_name 
                        ?: ($u->studentParent->guardian_name 
                        ?: $u->name));
                }

                return [
                    'id'         => $u->id,
                    'name'       => $displayName,
                    'email'      => $u->email ?? $u->username ?? $u->phone,
                    'user_type'  => $u->user_type,
                    'is_active'  => $u->is_active,
                    'avatar'     => $u->avatar,
                ];
            });

        // Recent logs (last 50 for the actor)
        $logs = ImpersonationLog::with(['impersonated.studentParent'])
            ->where('impersonator_id', $actor->id)
            ->latest('started_at')
            ->limit(50)
            ->get()
            ->map(function ($l) {
                $u = $l->impersonated;
                $impersonatedName = '—';
                
                if ($u) {
                    $impersonatedName = $u->name;
                    if ($u->user_type === 'parent' && $u->studentParent) {
                        $impersonatedName = $u->studentParent->father_name 
                            ?: ($u->studentParent->mother_name 
                            ?: ($u->studentParent->guardian_name 
                            ?: $u->name));
                    }
                }

                return [
                'id'               => $l->id,
                'impersonated_name' => $impersonatedName,
                'impersonated_type' => $l->impersonated_type,
                'ip_address'       => $l->ip_address,
                'started_at'       => $l->started_at?->toDateTimeString(),
                'ended_at'         => $l->ended_at?->toDateTimeString(),
                'duration_minutes' => $l->ended_at
                    ? round($l->started_at->diffInMinutes($l->ended_at))
                    : null,
                ];
            });

        return Inertia::render('Admin/Impersonation/Index', [
            'users'    => $users,
            'logs'     => $logs,
            'canImpersonate' => true,
        ]);
    }

    /**
     * Start impersonating a target user.
     */
    public function impersonate(Request $request, User $user)
    {
        $actor = $request->user();

        // Already impersonating? Deny (must exit first)
        abort_if(session()->has(self::KEY_IMPERSONATOR), 400, 'Please exit the current impersonation session first.');

        abort_unless($this->canImpersonate($actor), 403, 'You are not allowed to impersonate users.');

        // Cannot impersonate yourself
        abort_if($actor->id === $user->id, 422, 'You cannot impersonate yourself.');

        // Cannot impersonate a user with equal or higher role
        $actorLevel  = self::ROLE_HIERARCHY[$actor->user_type]  ?? 0;
        $targetLevel = self::ROLE_HIERARCHY[$user->user_type]   ?? 0;
        abort_unless($actorLevel > $targetLevel, 403, 'You cannot impersonate a user with an equal or higher role.');

        // School scoping: non-super-admin can only impersonate users in their own school
        if ($actor->user_type !== 'super_admin') {
            abort_unless($user->school_id === $actor->school_id, 403, 'You can only impersonate users within your school.');
        }

        abort_unless($user->is_active, 422, 'Target user account is inactive.');

        // Create audit log entry
        $log = ImpersonationLog::create([
            'impersonator_id'   => $actor->id,
            'impersonated_id'   => $user->id,
            'impersonator_type' => $actor->user_type,
            'impersonated_type' => $user->user_type,
            'ip_address'        => $request->ip(),
            'user_agent'        => $request->userAgent(),
            'started_at'        => now(),
        ]);

        // Store original session state
        session([
            self::KEY_IMPERSONATOR      => $actor->id,
            self::KEY_LOG_ID            => $log->id,
            self::KEY_IMPERSONATED_NAME => $user->name,
            self::KEY_STARTED_AT        => now()->timestamp,
        ]);

        // Switch the auth guard to the target user
        Auth::login($user);
        $request->session()->regenerate();

        // Restore impersonation session keys (regenerate clears the session)
        session([
            self::KEY_IMPERSONATOR      => $actor->id,
            self::KEY_LOG_ID            => $log->id,
            self::KEY_IMPERSONATED_NAME => $user->name,
            self::KEY_STARTED_AT        => now()->timestamp,
        ]);

        return redirect('/dashboard')->with('success', "Now logged in as {$user->name}. Click the banner above to return.");
    }

    /**
     * Exit the impersonation session, restoring the original admin.
     *
     * Graceful handling: if the session has already been cleared (e.g. auto-expired
     * by the middleware on a previous request), treat this as a no-op and redirect
     * safely rather than throwing a 400 error — the user is already back on their
     * real account at that point.
     */
    public function exit(Request $request)
    {
        $originalUserId = session(self::KEY_IMPERSONATOR);
        $logId          = session(self::KEY_LOG_ID);

        // ── Already expired / no active session ─────────────────────────────
        // This happens when the auto-expiry middleware cleared the session on a
        // prior request but the frontend banner hadn't refreshed yet.
        if (!$originalUserId) {
            // Nothing to restore — just redirect the now-restored user home.
            return redirect('/dashboard')->with('status', 'Your impersonation session has already ended.');
        }

        // ── Update audit log ─────────────────────────────────────────────────
        if ($logId) {
            ImpersonationLog::where('id', $logId)->update(['ended_at' => now()]);
        }

        // ── Clear impersonation session keys ─────────────────────────────────
        session()->forget([
            self::KEY_IMPERSONATOR,
            self::KEY_LOG_ID,
            self::KEY_IMPERSONATED_NAME,
            self::KEY_STARTED_AT,
        ]);

        // ── Restore original admin ────────────────────────────────────────────
        $original = User::find($originalUserId);

        if (!$original) {
            // Original user record deleted — just log out cleanly
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect('/login')->with('error', 'Original account could not be restored. Please log in again.');
        }

        Auth::login($original);
        $request->session()->regenerate();

        return redirect('/dashboard')->with('success', 'Returned to your account successfully.');
    }

    // ────────────────────────────────────────────────────────────────────

    private function canImpersonate(User $user): bool
    {
        return in_array($user->user_type, self::ALLOWED_IMPERSONATORS) 
               && $user->hasPermissionTo('impersonate_users');
    }

    // ── Statics for use in middleware / Inertia share ────────────────────

    public static function sessionKey(): string
    {
        return self::KEY_IMPERSONATOR;
    }

    public static function sessionNameKey(): string
    {
        return self::KEY_IMPERSONATED_NAME;
    }

    public static function sessionStartedAtKey(): string
    {
        return self::KEY_STARTED_AT;
    }

    public static function timeoutSeconds(): int
    {
        return self::TIMEOUT_SECONDS;
    }
}
