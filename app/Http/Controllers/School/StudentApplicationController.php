<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseClass;
use App\Models\StudentApplication;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\TransportStudentAllocation;
use App\Services\AdmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StudentApplicationController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $status   = $request->get('status', 'pending');

        $applications = StudentApplication::where('school_id', $schoolId)
            ->where('status', $status)
            ->with(['courseClass', 'section', 'academicYear'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        // Single query for all status counts instead of 3 separate COUNTs
        $rawCounts = StudentApplication::where('school_id', $schoolId)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return Inertia::render('School/Students/Applications/Index', [
            'applications' => $applications,
            'activeStatus' => $status,
            'counts' => [
                'pending'  => $rawCounts['pending']  ?? 0,
                'approved' => $rawCounts['approved'] ?? 0,
                'rejected' => $rawCounts['rejected'] ?? 0,
            ],
        ]);
    }

    public function create()
    {
        $schoolId = app('current_school_id');
        $classes  = CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name')->get();
        $routes   = TransportRoute::where('school_id', $schoolId)
            ->where('status', 'active')
            ->with(['stops' => fn($q) => $q->orderBy('stop_order')])
            ->get();

        $school         = \App\Models\School::find($schoolId);
        $standardMonths = (float) ($school?->settings['transport_standard_months'] ?? 10);
        if ($standardMonths <= 0) $standardMonths = 10.0;

        return Inertia::render('School/Students/Applications/Create', [
            'classes'        => $classes,
            'routes'         => $routes,
            'standardMonths' => $standardMonths,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $validated = $request->validate([
            'class_id'          => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'section_id'        => ['nullable', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'nullable|string|max:255',
            'dob'               => 'required|date',
            'birth_place'       => 'nullable|string|max:255',
            'mother_tongue'     => 'nullable|string|max:50',
            'gender'            => 'required|in:Male,Female,Other',
            'blood_group'       => 'nullable|string|max:10',
            'religion'          => 'nullable|string|max:50',
            'caste'             => 'nullable|string|max:50',
            'category'          => 'nullable|string|max:50',
            'aadhaar_no'               => 'nullable|digits:12',
            'nationality'              => 'nullable|string|max:100',
            'student_address'          => 'nullable|string',
            'city'                     => 'nullable|string|max:100',
            'state'                    => 'nullable|string|max:100',
            'pincode'                  => 'nullable|digits:6',
            'emergency_contact_name'   => 'nullable|string|max:255',
            'emergency_contact_phone'  => 'nullable|string|max:20',
            'photo'                    => 'nullable|image|max:5120',
            'primary_phone'            => 'required|string|max:20',
            'father_name'              => 'nullable|string|max:255',
            'mother_name'              => 'nullable|string|max:255',
            'guardian_name'            => 'nullable|string|max:255',
            'guardian_email'           => 'nullable|email|max:255',
            'guardian_phone'           => 'nullable|string|max:20',
            'father_phone'             => 'nullable|string|max:20',
            'mother_phone'             => 'nullable|string|max:20',
            'father_occupation'        => 'nullable|string|max:255',
            'father_qualification'     => 'nullable|string|max:100',
            'mother_occupation'        => 'nullable|string|max:255',
            'mother_qualification'     => 'nullable|string|max:100',
            'parent_address'           => 'nullable|string',
            'previous_school'          => 'nullable|string|max:255',
            'previous_class'           => 'nullable|string|max:50',
            'annual_income'            => 'nullable|string|max:100',
            // Transport (optional)
            'transport_route_id'       => 'nullable|exists:transport_routes,id',
            'transport_stop_id'        => 'nullable|exists:transport_stops,id',
            'transport_pickup_type'    => 'nullable|in:pickup,drop,both',
            'transport_months'         => 'nullable|integer|min:0|max:24',
            'transport_days'           => 'nullable|integer|min:0|max:30',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('applications/photos', 'public');
        }

        StudentApplication::create(array_merge($validated, [
            'school_id'       => $schoolId,
            'academic_year_id'=> $academicYearId,
            'photo'           => $photoPath,
            'status'          => 'pending',
            'submitted_at'    => now(),
        ]));

        return redirect()->route('school.registrations.index')
            ->with('success', 'Application submitted successfully! It is pending review.');
    }

    public function show(StudentApplication $registration)
    {
        abort_if($registration->school_id !== app('current_school_id'), 403);
        $registration->load(['courseClass', 'section', 'academicYear', 'reviewer']);

        return Inertia::render('School/Students/Applications/Show', [
            'application' => $registration,
        ]);
    }

    public function approve(StudentApplication $registration)
    {
        abort_if($registration->school_id !== app('current_school_id'), 403);
        if (!$registration->isPending()) {
            return back()->with('error', 'Only pending applications can be approved.');
        }

        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        // Map application fields into the format AdmissionService expects
        $data = array_merge($registration->toArray(), [
            'roll_no'        => null, // will be auto-generated
            'admission_date' => now()->format('Y-m-d'),
        ]);

        try {
            $admissionService = app(AdmissionService::class);
            $student = $admissionService->admitStudent($data, $schoolId, $academicYearId);

            $registration->update([
                'status'      => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
            ]);

            // Create transport allocation if route/stop was selected in the application
            if ($registration->transport_route_id && $registration->transport_stop_id) {
                $stop = \App\Models\TransportStop::find($registration->transport_stop_id);

                $school   = \App\Models\School::find($schoolId);
                $standard = (float) ($school?->settings['transport_standard_months'] ?? 10);
                if ($standard <= 0) $standard = 10.0;

                $months      = (int) ($registration->transport_months ?? (int) round($standard));
                $days        = (int) ($registration->transport_days   ?? 0);
                $monthsOpted = round($months + ($days / 30), 2);
                if ($monthsOpted <= 0) $monthsOpted = $standard;

                $fee = round(((float) ($stop?->fee ?? 0) / $standard) * $monthsOpted, 2);

                TransportStudentAllocation::create([
                    'school_id'      => $schoolId,
                    'student_id'     => $student->id,
                    'route_id'       => $registration->transport_route_id,
                    'stop_id'        => $registration->transport_stop_id,
                    'transport_fee'  => $fee,
                    'months_opted'   => $monthsOpted,
                    'pickup_type'    => $registration->transport_pickup_type ?? 'both',
                    'start_date'     => now()->format('Y-m-d'),
                    'status'         => 'active',
                    'amount_paid'    => 0,
                    'discount'       => 0,
                    'fine'           => 0,
                    'balance'        => $fee,
                    'payment_status' => $fee > 0 ? 'unpaid' : 'paid',
                ]);
            }

            return redirect()->route('school.students.show', $student)
                ->with('success', "✅ Application approved! Student {$student->first_name} has been admitted (#{$student->admission_no}).");
        } catch (\Throwable $e) {
            return back()->with('error', 'Approval failed: ' . $e->getMessage());
        }
    }

    public function edit(StudentApplication $registration)
    {
        abort_if($registration->school_id !== app('current_school_id'), 403);
        if (!$registration->isPending()) {
            return redirect()->route('school.registrations.show', $registration)
                ->with('error', 'Only pending applications can be edited.');
        }

        $schoolId = app('current_school_id');
        $classes  = CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name')->get();
        $routes   = TransportRoute::where('school_id', $schoolId)
            ->where('status', 'active')
            ->with(['stops' => fn($q) => $q->orderBy('stop_order')])
            ->get();
        $registration->load(['courseClass', 'section']);

        $school         = \App\Models\School::find($schoolId);
        $standardMonths = (float) ($school?->settings['transport_standard_months'] ?? 10);
        if ($standardMonths <= 0) $standardMonths = 10.0;

        return Inertia::render('School/Students/Applications/Edit', [
            'application'    => $registration,
            'classes'        => $classes,
            'routes'         => $routes,
            'standardMonths' => $standardMonths,
        ]);
    }

    public function update(Request $request, StudentApplication $registration)
    {
        abort_if($registration->school_id !== app('current_school_id'), 403);
        if (!$registration->isPending()) {
            return back()->with('error', 'Only pending applications can be edited.');
        }

        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'class_id'          => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'section_id'        => ['nullable', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'nullable|string|max:255',
            'dob'               => 'required|date',
            'birth_place'       => 'nullable|string|max:255',
            'mother_tongue'     => 'nullable|string|max:50',
            'gender'            => 'required|in:Male,Female,Other',
            'blood_group'       => 'nullable|string|max:10',
            'religion'          => 'nullable|string|max:50',
            'caste'             => 'nullable|string|max:50',
            'category'          => 'nullable|string|max:50',
            'aadhaar_no'               => 'nullable|digits:12',
            'nationality'              => 'nullable|string|max:100',
            'student_address'          => 'nullable|string',
            'city'                     => 'nullable|string|max:100',
            'state'                    => 'nullable|string|max:100',
            'pincode'                  => 'nullable|digits:6',
            'emergency_contact_name'   => 'nullable|string|max:255',
            'emergency_contact_phone'  => 'nullable|string|max:20',
            'photo'                    => 'nullable|image|max:5120',
            'primary_phone'            => 'required|string|max:20',
            'father_name'              => 'nullable|string|max:255',
            'mother_name'              => 'nullable|string|max:255',
            'guardian_name'            => 'nullable|string|max:255',
            'guardian_email'           => 'nullable|email|max:255',
            'guardian_phone'           => 'nullable|string|max:20',
            'father_phone'             => 'nullable|string|max:20',
            'mother_phone'             => 'nullable|string|max:20',
            'father_occupation'        => 'nullable|string|max:255',
            'father_qualification'     => 'nullable|string|max:100',
            'mother_occupation'        => 'nullable|string|max:255',
            'mother_qualification'     => 'nullable|string|max:100',
            'parent_address'           => 'nullable|string',
            'previous_school'          => 'nullable|string|max:255',
            'previous_class'           => 'nullable|string|max:50',
            'annual_income'            => 'nullable|string|max:100',
            // Transport (optional)
            'transport_route_id'       => 'nullable|exists:transport_routes,id',
            'transport_stop_id'        => 'nullable|exists:transport_stops,id',
            'transport_pickup_type'    => 'nullable|in:pickup,drop,both',
            'transport_months'         => 'nullable|integer|min:0|max:24',
            'transport_days'           => 'nullable|integer|min:0|max:30',
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('applications/photos', 'public');
        } else {
            unset($validated['photo']); // keep existing photo
        }

        $registration->update($validated);

        return redirect()->route('school.registrations.show', $registration)
            ->with('success', 'Application updated successfully.');
    }

    public function reject(Request $request, StudentApplication $registration)
    {
        abort_if($registration->school_id !== app('current_school_id'), 403);
        if (!$registration->isPending()) {
            return back()->with('error', 'Only pending applications can be rejected.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $registration->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'reviewed_at'      => now(),
            'reviewed_by'      => Auth::id(),
        ]);

        return redirect()->route('school.registrations.index')
            ->with('success', 'Application has been rejected.');
    }
}

