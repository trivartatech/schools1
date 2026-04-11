<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    // ── Helpers shared with auth domain ──────────────────────────────────────

    private function childList($user): array
    {
        $parent = $user->studentParent;
        if (!$parent) return [];

        return $parent->students()
            ->with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'name'         => $s->name,
                'admission_no' => $s->admission_no,
                'photo_url'    => $s->photo_url,
                'class'        => $s->currentAcademicHistory?->courseClass?->name ?? '',
                'section'      => $s->currentAcademicHistory?->section?->name ?? '',
            ])
            ->toArray();
    }

    // ── Profile ───────────────────────────────────────────────────────────────

    public function profile(Request $request): JsonResponse
    {
        $user   = $request->user()->load(['student', 'staff', 'studentParent']);
        $school = app('current_school');

        // Build safe user data — strip password, token, and the school relation (has API keys)
        $userData = $user->makeHidden(['password', 'remember_token'])->toArray();
        unset($userData['school']); // Remove school relation (contains sensitive settings/API keys)

        // Build safe school data (only public fields)
        $safeSchool = $school ? [
            'id'       => $school->id,
            'name'     => $school->name,
            'logo'     => $school->logo,
            'currency' => $school->currency,
        ] : null;

        // Include children list for parent users
        $children = $user->isParent() ? $this->childList($user) : [];

        return response()->json([
            'user'     => $userData,
            'school'   => $safeSchool,
            'children' => $children,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user      = $request->user();
        $validated = $request->validate([
            'name'   => 'sometimes|string|max:255',
            'phone'  => 'sometimes|string|max:20',
            'email'  => 'sometimes|email|unique:users,email,' . $user->id,
            'avatar' => 'sometimes|image|max:2048', // 2MB max
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            $file = $request->file('avatar');
            $safeName = 'avatar_' . $user->id . '_' . now()->timestamp . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("avatars/{$user->school_id}", $safeName, 'public');
            $validated['avatar'] = $path;

            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
        }

        unset($validated['avatar_file']); // cleanup
        $user->update($validated);
        return response()->json(['success' => true, 'user' => $user->fresh()]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $request->user()->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $request->user()->update(['password' => Hash::make($request->new_password)]);
        return response()->json(['success' => true]);
    }

    // ── Biometric PIN (server-side challenge) ─────────────────────────────────
    // The app stores biometric preference locally. For security, biometric login
    // still uses a standard token — we just skip the password screen on the device.
    // This endpoint issues a fresh short-lived token after the device confirms
    // biometric success, using the existing valid token as proof of identity.

    public function biometricChallenge(Request $request): JsonResponse
    {
        $user  = $request->user(); // must be authenticated
        $token = $user->createToken('mobile-biometric', ['*'], now()->addDays(30));

        return response()->json([
            'token'   => $token->plainTextToken,
            'expires' => $token->accessToken->expires_at?->toIso8601String(),
        ]);
    }

    // ── Device Registration ───────────────────────────────────────────────────

    public function registerDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token'   => 'required|string',
            'device_type' => 'required|in:mobile,tablet',
            'platform'    => 'required|in:android,ios',
        ]);
        $request->user()->update(['fcm_token' => $validated['fcm_token']]);
        return response()->json(['success' => true]);
    }
}
