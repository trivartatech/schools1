<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use App\Models\AcademicYear;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrganizationAdminController extends Controller
{
    /**
     * Display the Organization Admin Dashboard
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        
        // If not super admin, then Must belong to an organization
        if (!$user->organization_id && !$user->isSuperAdmin()) {
            abort(403, 'User is not assigned to any organization.');
        }

        $organizationId = $user->organization_id;
        $organization = $organizationId ? Organization::find($organizationId) : null;
        
        // Fetch schools
        $query = School::withoutGlobalScope('school');
        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }
        
        $schools = $query->withCount('students')->get();

        return Inertia::render('Admin/Organization/Dashboard', [
            'organization' => $organization ?? ['name' => 'Platform Administration (Global)'],
            'schools' => $schools,
            'stats' => [
                'total_schools' => $schools->count(),
                'total_students' => $schools->sum('students_count'),
                'total_revenue' => 0 
            ]
        ]);
    }

    /**
     * Show the create school form.
     */
    public function createSchool()
    {
        return Inertia::render('Admin/Organization/CreateSchool', [
            'timezones' => \DateTimeZone::listIdentifiers(),
            'boards' => ['CBSE', 'ICSE', 'State Board', 'IGCSE', 'IB'],
            'organizations' => Organization::all(),
        ]);
    }

    /**
     * Store the new school and initialize it.
     */
    public function storeSchool(Request $request)
    {
        $user = $request->user();

        $rules = [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:schools,code',
            'board' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'timezone' => 'required|string|max:100',
            'currency' => 'required|string|max:10',
            // Admin Details
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ];

        if ($user->isSuperAdmin()) {
            $rules['organization_id'] = 'required|exists:organizations,id';
        }

        $validated = $request->validate($rules);

        $orgId = $user->isSuperAdmin() ? $validated['organization_id'] : $user->organization_id;
        $org = Organization::findOrFail($orgId);

        DB::transaction(function () use ($validated, $user, $org, $orgId) {
            // 1. Create the school
            $school = School::create(array_merge($validated, [
                'organization_id' => $org->id,
                'slug' => Str::slug($validated['name']),
                'status' => 'active',
                'features' => [
                    'attendance' => true,
                    'fee' => true,
                    'exam' => true,
                    'communication' => true,
                    'transport' => true,
                    'hostel' => true,
                    'chat' => true,
                    'social_buzz' => true,
                    'classes' => true,
                    'staff' => true,
                    'payroll' => true,
                    'schedule' => true,
                    'academic' => true,
                    'front_office' => true,
                    'settings' => true,
                    'expense' => true,
                    'reports' => true,
                    'students' => true,
                ],
                'settings' => [
                    'footer_credit' => "© " . date('Y') . " " . $validated['name'] . ". All rights reserved.",
                ],
            ]));

            // 2. Create Default Academic Year (April - March)
            $school->academicYears()->create([
                'name' => date('Y') . '-' . (date('Y') + 1),
                'start_date' => date('Y') . '-04-01',
                'end_date' => (date('Y') + 1) . '-03-31',
                'is_current' => true,
                'status' => 'active'
            ]);

            // 3. Create the School Admin User
            $adminUser = User::create([
                'school_id' => $school->id,
                'organization_id' => $orgId,
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'username' => Str::slug($validated['admin_name']) . '.' . random_int(100, 999),
                'password' => Hash::make($validated['admin_password']),
                'user_type' => 'school_admin',
                'is_active' => true,
            ]);

            // 4. Assign the 'school_admin' role (if Spatie roles are synced)
            try {
                $adminUser->assignRole('school_admin');
            } catch (\Exception $e) {
                // Silently skip if role 'school_admin' doesn't exist yet
            }
        });

        return redirect()->route('org.admin.dashboard')->with('success', 'School created and initialized successfully.');
    }

    /**
     * Set the current school context for SuperAdmin
     */
    public function manageSchool(Request $request, School $school)
    {
        // Set the SuperAdmin session for ResolveTenant
        session(['superadmin_school_id' => $school->id]);
        
        // Clear any previous fixed academic year to let ResolveTenant pick the current one
        session()->forget('selected_academic_year_id');
        
        // Redirect to the school-level dashboard
        return redirect()->route('dashboard')->with('status', "Viewing context switched to: {$school->name}");
    }
}
