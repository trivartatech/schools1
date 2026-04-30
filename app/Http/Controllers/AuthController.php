<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    /**
     * Show the login page.
     */
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle authentication attempt.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'string'],
            'password' => ['required'],
        ]);

        $loginValue = trim($request->input('email'));
        $remember = $request->boolean('remember');

        // Sequence of fields to try for authentication
        $fields = ['username', 'phone'];
        if (filter_var($loginValue, FILTER_VALIDATE_EMAIL)) {
            array_unshift($fields, 'email');
        }

        foreach ($fields as $field) {
            if (Auth::attempt([$field => $loginValue, 'password' => $request->password], $remember)) {
                return $this->handleSuccessfulLogin($request);
            }
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Common logic after a successful login.
     */
    protected function handleSuccessfulLogin(Request $request)
    {
        $request->session()->regenerate();
        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            return back()->withErrors([
                'email' => 'Your account has been deactivated. Please contact administration.',
            ])->onlyInput('email');
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return Inertia::location(redirect()->intended('/dashboard')->getTargetUrl());
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location('/login');
    }

    /**
     * Request an OTP for phone number login (simulating Msg91).
     */
    public function requestOtp(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:15'],
        ]);

        $user = \App\Models\User::where('phone', $request->phone)->first();

        if (!$user) {
            return back()->withErrors([
                'phone' => 'No account found with this phone number.',
            ])->onlyInput('phone');
        }

        if (!$user->is_active) {
            return back()->withErrors([
                'phone' => 'This account has been deactivated.',
            ])->onlyInput('phone');
        }

        // Simulate generating a 6-digit OTP
        $otp = random_int(100000, 999999);
        
        // Send via NotificationService
        $school = $user->school;
        if ($school) {
            $notificationService = new \App\Services\NotificationService($school);
            $notificationService->notifyOtp($user, $otp);
        }
        
        \Illuminate\Support\Facades\Cache::put('otp_' . $request->phone, $otp, now()->addMinutes(5));

        return back()->with('status', 'OTP sent successfully. Please check your phone.');
    }

    /**
     * Verify the OTP and login the user.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $cachedOtp = \Illuminate\Support\Facades\Cache::get('otp_' . $request->phone);

        if (!$cachedOtp || (string)$cachedOtp !== (string)$request->otp) {
            return back()->withErrors([
                'otp' => 'The provided OTP is invalid or has expired.',
            ])->withInput($request->only('phone'));
        }

        $user = \App\Models\User::where('phone', $request->phone)->first();

        if ($user) {
            // Clear the OTP
            \Illuminate\Support\Facades\Cache::forget('otp_' . $request->phone);

            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            return redirect()->intended('/dashboard');
        }

        return back()->withErrors(['phone' => 'User not found.']);
    }

    // ── Mobile API Auth (Sanctum token-based) ─────────────────────────────────

    /**
     * POST /api/login — issue a 30-day Sanctum token for the mobile app.
     */
    public function apiLogin(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'email'        => 'required|string',
            'password'     => 'required|string',
            'device_token' => 'nullable|string',
            'device_type'  => 'nullable|string',
        ]);

        $loginValue = trim($request->input('email'));
        $fields     = ['username', 'phone'];
        if (filter_var($loginValue, FILTER_VALIDATE_EMAIL)) {
            array_unshift($fields, 'email');
        }

        $user = null;
        foreach ($fields as $field) {
            $candidate = \App\Models\User::where($field, $loginValue)->first();
            if ($candidate && \Illuminate\Support\Facades\Hash::check($request->password, $candidate->password)) {
                $user = $candidate;
                break;
            }
        }

        if (!$user) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }
        if (!$user->is_active) {
            return response()->json(['message' => 'Your account has been deactivated.'], 403);
        }

        if ($request->filled('device_token')) {
            $user->update(['fcm_token' => $request->device_token]);
        }

        $user->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);

        // Multi-device support: tag each token with a device fingerprint so the
        // same user can stay logged in on multiple phones / tablets at once.
        // If the same device logs in twice, we revoke only THAT device's previous
        // token — other devices are left untouched.
        $deviceName = trim((string) ($request->input('device_type') ?: $request->input('device_token') ?: ''));
        if ($deviceName === '') {
            $deviceName = 'mobile-' . substr(sha1((string) $request->userAgent() . $request->ip()), 0, 10);
        }
        // Namespace to avoid clashing with legacy "mobile" tokens from older clients
        $tokenName = 'mobile:' . substr($deviceName, 0, 120);

        $user->tokens()->where('name', $tokenName)->delete();
        $token = $user->createToken($tokenName, ['*'], now()->addDays(30));

        // Bundle school localization in the login response so the mobile app
        // can format dates / times / currency consistently from the first
        // screen, without an extra round-trip to /mobile/profile.
        $school = $user->school_id ? \App\Models\School::find($user->school_id) : null;
        $safeSchool = $school ? [
            'id'          => $school->id,
            'name'        => $school->name,
            'logo'        => $school->logo,
            'currency'    => $school->currency ?? '₹',
            'timezone'    => $school->timezone ?? 'Asia/Kolkata',
            'date_format' => $school->settings['date_format'] ?? 'DD/MM/YYYY',
            'time_format' => $school->settings['time_format'] ?? 'h:mm A',
        ] : null;

        return response()->json([
            'token'   => $token->plainTextToken,
            'expires' => $token->accessToken->expires_at?->toIso8601String(),
            'user'    => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'phone'     => $user->phone,
                'user_type' => $user->user_type,
                'avatar'    => $user->avatar,
                'school_id' => $user->school_id,
            ],
            'school'  => $safeSchool,
        ]);
    }

    /**
     * POST /api/mobile/logout — revoke the current token.
     */
    public function apiLogout(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * POST /api/scan-id-card-login
     *
     * Public endpoint — scan a student's printed/digital ID card QR and
     * sign in as their primary parent. No password, no OTP.
     *
     * Body: { payload, device_token?, device_type? }
     *   - payload: raw QR contents (either bare UUID or "/q/<uuid>" URL)
     *
     * SECURITY: anyone holding the ID card can log in. The card UUID is
     * a pure bearer credential; treat lost cards like lost passwords.
     * Throttled to 10/min by route middleware to slow UUID enumeration.
     *
     * Response shape mirrors apiLogin() so the mobile AuthContext can
     * reuse the same login pipeline (token + user + school).
     */
    public function apiScanIdCardLogin(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'payload'      => 'required|string|max:512',
            'device_token' => 'nullable|string',
            'device_type'  => 'nullable|string',
        ]);

        // Accept either bare UUID or "/q/<uuid>" URL — same shape the web
        // QRScanner.vue and the mobile rapidScanAttendance accept.
        $raw  = trim($request->input('payload'));
        $uuid = preg_match('~/q/([^/?#]+)~', $raw, $m) ? $m[1] : $raw;

        $student = \App\Models\Student::with(['studentParent.user', 'school'])
            ->where('uuid', $uuid)
            ->first();

        if (!$student) {
            return response()->json(['message' => 'QR code not recognised.'], 404);
        }

        $parentUser = $student->studentParent?->user;
        if (!$parentUser) {
            return response()->json([
                'message' => 'This student has no parent account linked. Please ask the school office to add one.',
            ], 422);
        }
        if (!$parentUser->is_active) {
            return response()->json([
                'message' => 'Parent account is deactivated. Please contact the school office.',
            ], 403);
        }

        if ($request->filled('device_token')) {
            $parentUser->update(['fcm_token' => $request->device_token]);
        }
        $parentUser->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Same multi-device token pattern as apiLogin so the parent can stay
        // signed in on multiple devices and a same-device re-scan revokes
        // only that device's previous token.
        $deviceName = trim((string) ($request->input('device_type') ?: $request->input('device_token') ?: ''));
        if ($deviceName === '') {
            $deviceName = 'mobile-' . substr(sha1((string) $request->userAgent() . $request->ip()), 0, 10);
        }
        $tokenName = 'mobile:' . substr($deviceName, 0, 120);

        $parentUser->tokens()->where('name', $tokenName)->delete();
        $token = $parentUser->createToken($tokenName, ['*'], now()->addDays(30));

        $school = $student->school
            ?? ($parentUser->school_id ? \App\Models\School::find($parentUser->school_id) : null);

        $safeSchool = $school ? [
            'id'          => $school->id,
            'name'        => $school->name,
            'logo'        => $school->logo,
            'currency'    => $school->currency ?? '₹',
            'timezone'    => $school->timezone ?? 'Asia/Kolkata',
            'date_format' => $school->settings['date_format'] ?? 'DD/MM/YYYY',
            'time_format' => $school->settings['time_format'] ?? 'h:mm A',
        ] : null;

        // Audit log — write a row so security review can spot abuse.
        \Illuminate\Support\Facades\Log::info('qr-login', [
            'student_id'      => $student->id,
            'student_uuid'    => $uuid,
            'parent_user_id'  => $parentUser->id,
            'school_id'       => $school?->id,
            'ip'              => $request->ip(),
            'user_agent'      => substr((string) $request->userAgent(), 0, 200),
        ]);

        return response()->json([
            'token'   => $token->plainTextToken,
            'expires' => $token->accessToken->expires_at?->toIso8601String(),
            'user'    => [
                'id'        => $parentUser->id,
                'name'      => $parentUser->name,
                'email'     => $parentUser->email,
                'phone'     => $parentUser->phone,
                'user_type' => $parentUser->user_type,
                'avatar'    => $parentUser->avatar,
                'school_id' => $parentUser->school_id,
            ],
            'school'  => $safeSchool,
            'student' => [
                'id'   => $student->id,
                'name' => $student->name,
            ],
        ]);
    }

    /**
     * POST /api/mobile/refresh — revoke current token and issue a fresh one.
     * Preserves the device-specific token name so multi-device sessions
     * remain independent.
     */
    public function refresh(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $user        = $request->user();
        $current     = $user->currentAccessToken();
        $previousName = $current?->name ?: 'mobile';
        $current?->delete();
        $token = $user->createToken($previousName, ['*'], now()->addDays(30));
        return response()->json(['token' => $token->plainTextToken]);
    }
}
