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

        // Revoke all previous mobile tokens and issue a fresh 30-day one
        $user->tokens()->where('name', 'mobile')->delete();
        $token = $user->createToken('mobile', ['*'], now()->addDays(30));

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
     * POST /api/mobile/refresh — revoke current token and issue a fresh one.
     */
    public function refresh(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()?->delete();
        $token = $user->createToken('mobile', ['*'], now()->addDays(30));
        return response()->json(['token' => $token->plainTextToken]);
    }
}
