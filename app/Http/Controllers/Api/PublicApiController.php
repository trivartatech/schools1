<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class PublicApiController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'School ERP API v1', 'version' => '1.0.0']);
    }

    /**
     * Return school configuration for mobile app discovery.
     * This endpoint is resolved via the ResolveTenant middleware (domain/header).
     */
    public function schoolConfig()
    {
        $school = null;

        // Try tenant-resolved school first
        try {
            $school = App::make('current_school');
        } catch (\Exception $e) {
            // No tenant context — fall back below
        }

        // Fallback: resolve from X-School-ID header or first active school
        if (!$school) {
            $headerSchoolId = request()->header('X-School-ID');
            if ($headerSchoolId) {
                $school = \App\Models\School::where('id', $headerSchoolId)->where('status', 'active')->first();
            }
        }
        if (!$school) {
            $school = \App\Models\School::where('status', 'active')->first();
        }

        if (!$school) {
            return response()->json([
                'success' => false,
                'message' => 'School not found or invalid URL.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSchoolResponse($school)
        ]);
    }

    /**
     * Return school configuration for mobile app discovery.
     * This endpoint is resolved via the ResolveTenant middleware (domain/header).
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $school = App::make('current_school');
        
        if (!$school) {
            return response()->json(['success' => false, 'message' => 'Invalid school context.'], 400);
        }

        $user = \App\Models\User::where('email', $request->email)
            ->where('school_id', $school->id)
            ->first();

        if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials for ' . $school->name
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json(['success' => false, 'message' => 'Your account is currently inactive.'], 403);
        }

        // Create token for the mobile device
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->user_type,
            ],
            'token' => $token
        ]);
    }

    /**
     * Discover a school by its short code.
     */
    public function discover(Request $request)
    {
        $code = $request->query('code');

        if (!$code) {
            return response()->json(['success' => false, 'message' => 'School code is required.'], 400);
        }

        $school = \App\Models\School::where('code', $code)->where('status', 'active')->first();

        if (!$school) {
            return response()->json(['success' => false, 'message' => 'Invalid school code.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSchoolResponse($school)
        ]);
    }

    /**
     * Standardize the school discovery response (v2.0 Improvised).
     */
    private function formatSchoolResponse($school)
    {
        return [
            'status'              => $school->status, // 'active', 'inactive', 'maintenance'
            'school_name'         => $school->name,
            'slug'                => $school->slug,
            'backend_url'         => config('app.url'),
            'api_version'         => 'v2', // Handshake for mobile app compatibility
            'logo_url'            => $school->logo ? asset('storage/' . $school->logo) : null,
            'icon_url'            => isset($school->settings['icon']) ? asset('storage/' . $school->settings['icon']) : null,
            'maintenance_message' => $school->settings['maintenance_message'] ?? null,
            'branding'            => [
                'primary'   => $school->settings['theme']['primary'] ?? '#6366f1',
                'secondary' => $school->settings['theme']['secondary'] ?? '#4f46e5',
                'theme_mode' => $school->settings['theme']['mode'] ?? 'light', // support dark/light
            ],
            // Dynamic modules licensed to this specific school
            'modules'             => $this->getEnabledModules($school),
            'id'                  => $school->id,
        ];
    }

    /**
     * Get a list of modules enabled for the given school.
     */
    private function getEnabledModules($school)
    {
        // Default set of fundamental modules
        $modules = ['attendance', 'timetable', 'exams'];

        // Add additional modules based on school features/license
        if ($school->hasFeature('fees')) $modules[] = 'fees';
        if ($school->hasFeature('transport')) $modules[] = 'transport';
        if ($school->hasFeature('library')) $modules[] = 'library';
        if ($school->hasFeature('canteen')) $modules[] = 'canteen';

        return $modules;
    }
}
