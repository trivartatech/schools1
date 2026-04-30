<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EditRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Utils\ActivityLog;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $statusFilter = $request->query('status', 'current');
        $search = $request->query('search');

        $query = Staff::tenant()->with(['user', 'department', 'designation']);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->whereHas('user', function($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                       ->orWhere('phone', 'like', "%{$search}%");
                })->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if ($statusFilter === 'current') {
            $query->whereIn('status', ['active', 'on_leave']);
        } else {
            $query->whereIn('status', ['inactive', 'resigned', 'terminated']);
        }

        // ── Sort — allowlist prevents arbitrary column ordering ─────────────
        $sortMap = [
            'name'         => 'users.name',
            'employee_id'  => 'staff.employee_id',
            'department'   => 'departments.name',
            'joining_date' => 'staff.joining_date',
            'status'       => 'staff.status',
        ];
        $sortKey = $request->input('sort');
        $sortDir = strtolower($request->input('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sortKey && isset($sortMap[$sortKey])) {
            if ($sortKey === 'name') {
                $query->leftJoin('users', 'staff.user_id', '=', 'users.id')
                      ->select('staff.*');
            } elseif ($sortKey === 'department') {
                $query->leftJoin('departments', 'staff.department_id', '=', 'departments.id')
                      ->select('staff.*');
            }
            $query->orderBy($sortMap[$sortKey], $sortDir)
                  ->orderBy('staff.id', 'asc');
        } else {
            $query->latest('staff.created_at');
        }

        $staff = $query->paginate(15)->withQueryString();

        return Inertia::render('School/Staff/Index', [
            'staff' => $staff,
            'filters' => [
                'status' => $statusFilter,
                'search' => $search,
                'sort'   => $sortKey,
                'dir'    => $sortDir,
            ],
        ]);
    }

    public function create()
    {
        $schoolId = app('current_school_id');
        $school = \App\Models\School::find($schoolId);
        $settings = $school?->settings ?? [];

        // Generate Employee ID similar to Admission Number
        $empPrefix    = $settings['emp_prefix']    ?? 'EMP-';
        $empSuffix    = $settings['emp_suffix']    ?? '';
        $empStartNo   = (int) ($settings['emp_start_no']   ?? 1);
        $empPadLength = (int) ($settings['emp_pad_length'] ?? 4);

        // Resolve date/year tokens reusing logic
        $empPrefix = $this->resolveTokens($empPrefix, $school);
        $empSuffix = $this->resolveTokens($empSuffix, $school);

        $empCount    = Staff::withTrashed()->where('school_id', $schoolId)->count() + $empStartNo;
        $generatedEmpId = $empPrefix . str_pad($empCount, $empPadLength, '0', STR_PAD_LEFT) . $empSuffix;
        
        return Inertia::render('School/Staff/Create', [
            'departments'          => Department::where('school_id', $schoolId)->get(),
            'designations'         => Designation::where('school_id', $schoolId)->where('is_active', true)->get(),
            'generated_employee_id'=> $generatedEmpId,
            'available_roles'      => $this->staffRoles(),
        ]);
    }

    /**
     * Helper to resolve dynamic date/year tokens in prefix/suffix
     */
    protected function resolveTokens(string $template, ?\App\Models\School $school): string
    {
        $now = \Carbon\Carbon::now();
        $ayShort = '??-??';
        if ($school) {
            $ay = \App\Models\AcademicYear::where('school_id', $school->id)->where('is_current', true)->first();
            if ($ay) {
                $parts   = explode('-', $ay->name);
                $ayShort = count($parts) === 2 ? $parts[0] . '-' . $parts[1] : $ay->name;
            }
        }
        return str_replace(
            ['{YEAR}', '{YY}', '{MONTH}', '{MM}', '{MON}', '{DD}', '{AY}'],
            [$now->format('Y'), $now->format('y'), $now->format('m'), $now->format('m'),
             strtoupper($now->format('M')), $now->format('d'), $ayShort],
            $template
        );
    }

    public function show(Staff $staff)
    {
        abort_if($staff->school_id !== app('current_school_id'), 403);

        $staff->load(['user', 'department', 'designation.parent']);

        $schoolId = app('current_school_id');

        // Load leave counts (tenant-scoped)
        $leaveStats = \App\Models\Leave::where('school_id', $schoolId)
            ->where('user_id', $staff->user_id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Load recent payrolls (tenant-scoped)
        $payrolls = \App\Models\Payroll::where('school_id', $schoolId)
            ->where('staff_id', $staff->id)
            ->orderByDesc('year')->orderByDesc('month')
            ->take(6)->get();

        return Inertia::render('School/Staff/Show', [
            'staff'      => $staff,
            'leaveStats' => $leaveStats,
            'payrolls'   => $payrolls,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:users,email',
            'phone'            => 'required|string|max:20',
            'username'         => 'nullable|string|max:100|unique:users,username',
            'password'         => 'nullable|string|min:6',
            'role'             => 'required|string|in:' . implode(',', array_keys($this->staffRoles())),
            'employee_id'      => 'required|string|max:50|unique:staff,employee_id',
            'department_id'    => ['nullable', Rule::exists('departments', 'id')->where('school_id', $schoolId)],
            'designation_id'   => ['nullable', Rule::exists('designations', 'id')->where('school_id', $schoolId)],
            'joining_date'     => 'nullable|date',
            'qualification'    => 'nullable|string|max:255',
            'experience_years' => 'nullable|numeric|min:0',
            'basic_salary'     => 'nullable|numeric|min:0',
            'bank_name'        => 'nullable|string|max:255',
            'bank_account_no'  => 'nullable|string|max:100',
            'ifsc_code'        => 'nullable|string|max:50',
            'pan_no'           => 'nullable|string|max:50',
            'epf_no'           => 'nullable|string|max:50',
            'photo'            => 'nullable|image|max:5120',
            'signature'        => 'nullable|image|max:2048',
        ]);

        $user = DB::transaction(function () use ($validated, $request, $schoolId) {
            $user = \App\Models\User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'phone'     => $validated['phone'],
                'username'  => $validated['username'] ?? null,
                'user_type' => $this->roleToUserType($validated['role']),
                'school_id' => $schoolId,
                'password'  => \Illuminate\Support\Facades\Hash::make($validated['password'] ?? \Illuminate\Support\Str::random(10)),
                'is_active' => true,
            ]);

            $user->syncRoles([$validated['role']]);

            Staff::create([
                'school_id' => $schoolId,
                'user_id' => $user->id,
                'department_id' => $validated['department_id'],
                'designation_id' => $validated['designation_id'],
                'employee_id' => $validated['employee_id'],
                'qualification' => $validated['qualification'],
                'experience_years' => $validated['experience_years'] ?? 0,
                'joining_date' => $validated['joining_date'],
                'basic_salary' => $validated['basic_salary'] ?? 0,
                'bank_name' => $validated['bank_name'],
                'bank_account_no' => $validated['bank_account_no'],
                'ifsc_code' => $validated['ifsc_code'],
                'pan_no' => $validated['pan_no'],
                'epf_no' => $validated['epf_no'],
                'status' => 'active',
                'photo' => $request->hasFile('photo') ? $request->file('photo')->store('staff/photos', 'public') : null,
                'signature' => $request->hasFile('signature') ? $request->file('signature')->store('staff/signatures', 'public') : null,
            ]);

            return $user;
        });

        ActivityLog::staff("Created new staff member: {$validated['name']} ({$validated['employee_id']})", $user);

        return redirect()->route('school.staff.index')->with('success', 'Staff member added successfully.');
    }

    public function edit(Staff $staff)
    {
        abort_if($staff->school_id !== app('current_school_id'), 403);

        $staff->load(['user', 'user.roles']);
        $schoolId = app('current_school_id');

        return Inertia::render('School/Staff/Edit', [
            'staff'           => array_merge($staff->toArray(), [
                'current_role' => $staff->user?->roles->first()?->name ?? 'teacher',
            ]),
            'departments'     => Department::where('school_id', $schoolId)->get(),
            'designations'    => Designation::where('school_id', $schoolId)->where('is_active', true)->get(),
            'available_roles' => $this->staffRoles(),
        ]);
    }

    public function update(Request $request, Staff $staff)
    {
        abort_if($staff->school_id !== app('current_school_id'), 403);

        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'phone'            => 'required|string|max:20',
            'username'         => ['nullable', 'string', 'max:100', Rule::unique('users', 'username')->ignore($staff->user_id)],
            'password'         => 'nullable|string|min:6',
            'role'             => 'required|string|in:' . implode(',', array_keys($this->staffRoles())),
            'department_id'    => ['nullable', Rule::exists('departments', 'id')->where('school_id', $schoolId)],
            'designation_id'   => ['nullable', Rule::exists('designations', 'id')->where('school_id', $schoolId)],
            'joining_date'     => 'nullable|date',
            'qualification'    => 'nullable|string|max:255',
            'experience_years' => 'nullable|numeric|min:0',
            'basic_salary'     => 'nullable|numeric|min:0',
            'bank_name'        => 'nullable|string|max:255',
            'bank_account_no'  => 'nullable|string|max:100',
            'ifsc_code'        => 'nullable|string|max:50',
            'pan_no'           => 'nullable|string|max:50',
            'epf_no'           => 'nullable|string|max:50',
            'status'           => 'required|in:active,inactive,on_leave,resigned,terminated',
            'photo'            => 'nullable|image|max:5120',
            'signature'        => 'nullable|image|max:2048',
        ]);

        $updateData = [
            'department_id' => $validated['department_id'],
            'designation_id' => $validated['designation_id'],
            'qualification' => $validated['qualification'],
            'experience_years' => $validated['experience_years'] ?? 0,
            'joining_date' => $validated['joining_date'],
            'basic_salary' => $validated['basic_salary'] ?? 0,
            'bank_name' => $validated['bank_name'],
            'bank_account_no' => $validated['bank_account_no'],
            'ifsc_code' => $validated['ifsc_code'],
            'pan_no' => $validated['pan_no'],
            'epf_no' => $validated['epf_no'],
            'status' => $validated['status'],
        ];

        if ($request->hasFile('photo')) {
            if ($staff->photo) {
                Storage::disk('public')->delete($staff->photo);
            }
            $updateData['photo'] = $request->file('photo')->store('staff/photos', 'public');
        }

        if ($request->hasFile('signature')) {
            if ($staff->signature) {
                Storage::disk('public')->delete($staff->signature);
            }
            $updateData['signature'] = $request->file('signature')->store('staff/signatures', 'public');
        }

        DB::transaction(function () use ($staff, $validated, $updateData) {
            $userUpdate = [
                'name'      => $validated['name'],
                'phone'     => $validated['phone'],
                'username'  => $validated['username'] ?? null,
                'user_type' => $this->roleToUserType($validated['role']),
                'is_active' => $validated['status'] === 'active',
            ];
            if (!empty($validated['password'])) {
                $userUpdate['password'] = \Illuminate\Support\Facades\Hash::make($validated['password']);
            }
            $staff->user->update($userUpdate);
            $staff->user->syncRoles([$validated['role']]);
            $staff->update($updateData);
        });

        ActivityLog::staff("Updated staff member profile: {$staff->user->name}", $staff->user);

        return redirect()->route('school.staff.index')->with('success', 'Staff member updated successfully.');
    }

    /**
     * GET /school/staff/qr-codes/excel
     * Bulk export of every active staff member with embedded QR codes.
     */
    public function exportQrCodesExcel(Request $request)
    {
        $schoolId     = app('current_school_id');
        $departmentId = $request->integer('department_id') ?: null;

        $fileName = 'Staff_QRs_' . date('Y_m_d_His') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\StaffQRExport($schoolId, $departmentId),
            $fileName
        );
    }

    /**
     * GET /school/staff/qr-codes/pdf
     * Print-ready PDF — 8 badges per A4 page, each with name, designation,
     * employee ID and a 26mm QR linking to /q/staff/<employee_id>.
     */
    public function exportQrCodesPdf(Request $request)
    {
        $schoolId     = app('current_school_id');
        $departmentId = $request->integer('department_id') ?: null;
        $school       = app('current_school');

        $staffList = Staff::with(['user:id,name,phone', 'designation:id,name', 'department:id,name'])
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereNotNull('employee_id')
            ->when($departmentId, fn($q) => $q->where('department_id', $departmentId))
            ->orderBy('employee_id')
            ->get();

        $writer = new \Endroid\QrCode\Writer\PngWriter();
        $rows = $staffList->map(function ($s) use ($writer) {
            $url = url('/q/staff/' . $s->employee_id);
            $qr  = new \Endroid\QrCode\QrCode($url);
            $qr->setSize(280);
            $qr->setMargin(6);
            $png = $writer->write($qr)->getString();
            return [
                'employee_id' => $s->employee_id,
                'name'        => $s->user?->name ?? '—',
                'designation' => $s->designation?->name ?? '—',
                'department'  => $s->department?->name,
                'phone'       => $s->user?->phone,
                'qr_data_uri' => 'data:image/png;base64,' . base64_encode($png),
            ];
        })->all();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.staff-badges', [
            'rows'    => $rows,
            'school'  => $school,
            'title'   => ($school?->name ?? 'School') . ' — Staff Badges',
            'printed' => now()->format('d M Y, h:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('staff-badges-' . now()->format('Y-m-d_His') . '.pdf');
    }

    public function destroy(Staff $staff)
    {
        abort_if($staff->school_id !== app('current_school_id'), 403);

        $user = $staff->user;
        $userName = $user?->name ?? 'Unknown';

        DB::transaction(function () use ($staff, $user) {
            $staff->delete();
            if ($user) {
                $user->roles()->detach();
                $user->delete();
            }
        });

        ActivityLog::staff("Deleted staff member: {$userName}", $user);

        return redirect()->route('school.staff.index')->with('success', 'Staff member deleted successfully.');
    }

    /**
     * Map a Spatie role slug to a valid user_type for the DB/enum.
     * Only super_admin / school_admin / principal / teacher / accountant
     * are in both the DB enum and the PHP UserType enum. Everything else
     * uses `teacher` as a generic staff user_type — the Spatie role
     * controls the actual permissions.
     */
    private function roleToUserType(string $role): string
    {
        return match ($role) {
            'admin'      => 'school_admin',
            'accountant' => 'accountant',
            'teacher'    => 'teacher',
            default      => 'teacher',
        };
    }

    /**
     * Roles that can be assigned to staff members.
     * Returns ['slug' => 'Label'] used for the dropdown and server-side validation.
     */
    private function staffRoles(): array
    {
        return [
            'admin'             => 'Admin / Principal',
            'teacher'           => 'Teacher',
            'accountant'        => 'Accountant / Finance',
            'librarian'         => 'Librarian',
            'receptionist'      => 'Receptionist / Front Office',
            'hr'                => 'HR Manager',
            'transport_manager' => 'Transport Manager',
            'driver'            => 'Driver',
            'conductor'         => 'Conductor',
            'hostel_warden'     => 'Hostel Warden',
            'nurse'             => 'School Nurse / Medic',
            'it_support'        => 'IT Support',
            'auditor'           => 'External Auditor (View Only)',
        ];
    }

    /**
     * Show form to edit Salary & Compliance details
     */
    public function salaryForm(Staff $staff)
    {
        abort_if($staff->school_id !== app('current_school_id'), 403);

        $staff->load('user');
        return Inertia::render('School/Staff/Salary', [
            'staff' => clone $staff,
        ]);
    }

    /**
     * Update Salary & Compliance details
     */
    public function updateSalary(Request $request, Staff $staff)
    {
        abort_if($staff->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'basic_salary'    => 'required|numeric|min:0',
            'bank_name'       => 'nullable|string|max:255',
            'bank_account_no' => 'nullable|string|max:255',
            'ifsc_code'       => 'nullable|string|max:20',
            'pan_no'          => 'nullable|string|max:20',
            'epf_no'          => 'nullable|string|max:50',
            'allowances_config' => 'nullable|array',
            'deductions_config' => 'nullable|array',
            'tax_config'        => 'nullable|array',
        ]);

        $staff->update($validated);

        ActivityLog::finance("Updated salary & compliance details for: {$staff->user->name}", $staff);

        return redirect()->route('school.staff.show', $staff->id)
            ->with('success', 'Salary & compliance details updated successfully.');
    }

    public function createRequest(Staff $staff)
    {
        abort_if($staff->school_id !== app('current_school_id'), 403);

        $user = auth()->user();
        if ($staff->user_id !== $user->id) {
            abort_unless($user->can('request_edit_staff') || $user->can('edit_staff'), 403);
        }

        $staff->load('user');
        
        return Inertia::render('School/Staff/RequestEdit', [
            'staff' => clone $staff,
            'departments' => Department::where('school_id', app('current_school_id'))->get(),
            'designations' => Designation::where('school_id', app('current_school_id'))->where('is_active', true)->get(),
        ]);
    }

    /**
     * Store the requested profile edits.
     */
    public function storeRequest(Request $request, Staff $staff)
    {
        abort_if($staff->school_id !== app('current_school_id'), 403);

        $user = auth()->user();
        if ($staff->user_id !== $user->id) {
            abort_unless($user->can('request_edit_staff') || $user->can('edit_staff'), 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('school_id', app('current_school_id'))],
            'designation_id' => ['nullable', Rule::exists('designations', 'id')->where('school_id', app('current_school_id'))],
            'joining_date' => 'nullable|date',
            'qualification' => 'nullable|string|max:255',
            'experience_years' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_no' => 'nullable|string|max:100',
            'ifsc_code' => 'nullable|string|max:50',
            'pan_no' => 'nullable|string|max:50',
            'epf_no' => 'nullable|string|max:50',
            'reason' => 'nullable|string|max:1000'
        ]);

        // Extract the reason and filter out identical values to current DB values
        $reason = $request->input('reason');
        unset($validated['reason']);

        $requestedChanges = [];
        
        // Helper to check and add if changed
        $checkVal = function($key, $oldVal, $newVal) use (&$requestedChanges) {
            // Treat empty strings from form as null if old value was null
            if ($newVal === '') $newVal = null;
            
            // Note: simple == comparison to handle int vs string IDs
            if ($newVal !== null && $oldVal != $newVal) {
                $requestedChanges[$key] = $newVal;
            }
        };

        // User changes
        $checkVal('name', $staff->user->name, $validated['name'] ?? null);
        $checkVal('phone', $staff->user->phone, $validated['phone'] ?? null);

        // Staff changes
        $checkVal('department_id', $staff->department_id, $validated['department_id'] ?? null);
        $checkVal('designation_id', $staff->designation_id, $validated['designation_id'] ?? null);
        $checkVal('joining_date', $staff->joining_date ? \Carbon\Carbon::parse($staff->joining_date)->format('Y-m-d') : null, $validated['joining_date'] ?? null);
        $checkVal('qualification', $staff->qualification, $validated['qualification'] ?? null);
        $checkVal('experience_years', $staff->experience_years, $validated['experience_years'] ?? null);
        $checkVal('bank_name', $staff->bank_name, $validated['bank_name'] ?? null);
        $checkVal('bank_account_no', $staff->bank_account_no, $validated['bank_account_no'] ?? null);
        $checkVal('ifsc_code', $staff->ifsc_code, $validated['ifsc_code'] ?? null);
        $checkVal('pan_no', $staff->pan_no, $validated['pan_no'] ?? null);
        $checkVal('epf_no', $staff->epf_no, $validated['epf_no'] ?? null);

        if (empty($requestedChanges)) {
            return back()->with('error', 'No actual changes were detected. Request not submitted.');
        }

        EditRequest::create([
            'school_id' => app('current_school_id'),
            'user_id' => auth()->id(), // the person submitting it
            'requestable_type' => Staff::class,
            'requestable_id' => $staff->id,
            'requested_changes' => $requestedChanges,
            'reason' => $reason,
            'status' => 'pending'
        ]);

        return redirect()->route('school.staff.show', $staff->id)
            ->with('success', 'Profile update request submitted successfully. It is now pending admin approval.');
    }
}
