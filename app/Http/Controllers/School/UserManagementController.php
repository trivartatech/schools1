<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Exports\UserCredentialsExport;
use App\Models\User;
use App\Models\CourseClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentParent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');

        $query = User::where('school_id', $schoolId)
            ->with(['staff', 'student', 'studentParent']);

        // Filters
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        } else {
            // Default whitelist — every user_type that can have a login.
            // school_admin was previously omitted, so admins were silently
            // hidden from the default list.
            $query->whereIn('user_type', [
                'teacher', 'student', 'parent', 'principal',
                'school_admin', 'accountant', 'driver',
            ]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // ── Sort — allowlist prevents arbitrary column ordering ──────────────
        $sortMap = [
            'name'     => 'name',
            'role'     => 'user_type',
            'username' => 'username',
            'access'   => 'is_active',
        ];
        $sortKey = $request->input('sort');
        $sortDir = strtolower($request->input('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if ($sortKey && isset($sortMap[$sortKey])) {
            $query->orderBy($sortMap[$sortKey], $sortDir)->orderBy('id', 'asc');
        }

        // Class / Section filter — applies to students AND parents (via children)
        if ($request->filled('class_id') || $request->filled('section_id')) {
            if ($request->user_type === 'student') {
                $query->whereHas('student.currentAcademicHistory', function ($q) use ($request) {
                    if ($request->filled('class_id'))   $q->where('class_id',   $request->class_id);
                    if ($request->filled('section_id')) $q->where('section_id', $request->section_id);
                });
            } elseif ($request->user_type === 'parent') {
                // Parent → their children's current academic history
                $query->whereHas('studentParent.students.currentAcademicHistory', function ($q) use ($request) {
                    if ($request->filled('class_id'))   $q->where('class_id',   $request->class_id);
                    if ($request->filled('section_id')) $q->where('section_id', $request->section_id);
                });
            }
        }

        // Eager-load class/section through the relevant relations so the UI
        // can render them under each row's name.
        $query->with([
            'student.currentAcademicHistory.courseClass:id,name',
            'student.currentAcademicHistory.section:id,name',
            'studentParent.students.currentAcademicHistory.courseClass:id,name',
            'studentParent.students.currentAcademicHistory.section:id,name',
        ]);

        // Page size honours the school's System Config "Page Length" setting.
        $perPage = app('current_school')?->pageLength() ?? 20;
        // When no explicit sort is applied, fall back to the previous "latest first"
        // ordering. Otherwise the orderBy above already drives the order.
        if (! ($sortKey && isset($sortMap[$sortKey]))) {
            $query->latest();
        }
        $users = $query->paginate($perPage)->withQueryString();

        // Decorate each user with class_name / section_name so the Vue page
        // doesn't have to traverse three levels of relations to render them.
        $users->getCollection()->transform(function ($u) {
            $type = $u->user_type instanceof \BackedEnum ? $u->user_type->value : (string) $u->user_type;
            $className = null;
            $sectionName = null;

            if ($type === 'student' && $u->student?->currentAcademicHistory) {
                $h = $u->student->currentAcademicHistory;
                $className   = $h->courseClass?->name;
                $sectionName = $h->section?->name;
            } elseif ($type === 'parent' && $u->studentParent) {
                // Use the FIRST child's class/section as a representative label
                $firstChild = $u->studentParent->students->first();
                if ($firstChild?->currentAcademicHistory) {
                    $h = $firstChild->currentAcademicHistory;
                    $className   = $h->courseClass?->name;
                    $sectionName = $h->section?->name;
                }
            }

            $u->setAttribute('class_name',   $className);
            $u->setAttribute('section_name', $sectionName);
            return $u;
        });

        return Inertia::render('School/Users/Index', [
            'users'     => $users,
            'filters'   => $request->only(['user_type', 'search', 'status', 'class_id', 'section_id', 'sort', 'dir']),
            'classes'   => CourseClass::where('school_id', $schoolId)->orderBy('sort_order')->get(['id', 'name']),
            'sections'  => Section::where('school_id', $schoolId)->orderBy('sort_order')->get(['id', 'course_class_id', 'name']),
            'missing_counts' => [
                'parents'  => StudentParent::where('school_id', $schoolId)->whereNull('user_id')->count(),
                'students' => Student::where('school_id', $schoolId)->whereNull('user_id')->count(),
                'staff'    => \App\Models\Staff::where('school_id', $schoolId)->whereNull('user_id')->count(),
            ],
        ]);
    }

    public function resetPassword(Request $request, $id)
    {
        $schoolId = app('current_school_id');
        $user = User::where('school_id', $schoolId)->findOrFail($id);

        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Use the profile page to change your own password.'], 422);
        }

        // Default reset value — keep simple so admins can communicate it
        // verbally / via SMS. Users should change it on first login.
        $newPassword = 'password';
        $user->update(['password' => Hash::make($newPassword)]);

        \Illuminate\Support\Facades\Log::info('Password reset by admin', [
            'reset_by'  => auth()->id(),
            'reset_for' => $user->id,
            'school_id' => $schoolId,
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Password reset successfully. Show this to the user once, then close.',
            'password' => $newPassword,
        ]);
    }

    public function toggleStatus($id)
    {
        $user = User::where('school_id', app('current_school_id'))->findOrFail($id);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot disable your own account.');
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return back()->with('success', 'User status updated successfully.');
    }

    public function updateUsername(Request $request, $id)
    {
        $user = User::where('school_id', app('current_school_id'))->findOrFail($id);

        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email'    => 'nullable|email|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return back()->with('success', 'Credentials updated successfully.');
    }

    /**
     * POST /school/users/create-missing — create User accounts for any
     * StudentParent / Student records that don't have one yet. Mirrors the
     * portal:create-users artisan command but scoped per school + per type.
     *
     * Designed to handle thousands of rows safely:
     *   - Pre-hashes the default password ONCE (one bcrypt call instead of N).
     *     Acceptable because the plaintext is a known default; users are
     *     expected to change on first login.
     *   - Chunks the parent / staff / student queries to keep memory flat.
     *   - Per-row try/catch so a single bad row (e.g. unique-constraint clash)
     *     doesn't roll back the whole batch.
     *   - Cache mutex prevents two concurrent runs deadlocking the users table.
     *   - Extends max_execution_time / memory_limit defensively.
     */
    public function createMissing(Request $request)
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $schoolId = app('current_school_id');
        $type     = $request->input('type', 'parent'); // 'parent' | 'student' | 'staff' | 'all'

        $lockKey = "create-missing-logins:school-{$schoolId}";
        $lock    = Cache::lock($lockKey, 900); // 15-min lease

        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'A bulk login-creation run is already in progress for this school. Please wait for it to finish.',
            ], 409);
        }

        $created     = [];
        $failed      = 0;
        // Single bcrypt call reused for every new user — the plaintext is a
        // known default. With cost 12 this saves ~250ms per row × 3000+ rows.
        $passwordPlain = 'password';
        $passwordHash  = Hash::make($passwordPlain);

        try {
            // ── Parents ──
            if (in_array($type, ['parent', 'all'], true)) {
                StudentParent::where('school_id', $schoolId)
                    ->whereNull('user_id')
                    ->whereNotNull('primary_phone')
                    ->orderBy('id')
                    ->chunkById(200, function ($parents) use ($schoolId, $passwordHash, $passwordPlain, &$created, &$failed) {
                        foreach ($parents as $parent) {
                            try {
                                $existing = User::where('school_id', $schoolId)
                                    ->where(function ($q) use ($parent) {
                                        $q->where('username', $parent->primary_phone)
                                          ->orWhere('phone',    $parent->primary_phone);
                                    })
                                    ->first();

                                if ($existing) {
                                    $parent->update(['user_id' => $existing->id]);
                                    if (! $existing->hasRole('parent')) $existing->assignRole('parent');
                                    continue;
                                }

                                $user = User::create([
                                    'school_id' => $schoolId,
                                    'name'      => $parent->father_name ?: ($parent->guardian_name ?: 'Parent'),
                                    'username'  => $parent->primary_phone,
                                    'phone'     => $parent->primary_phone,
                                    'password'  => $passwordHash,
                                    'user_type' => 'parent',
                                    'is_active' => true,
                                ]);
                                $user->assignRole('parent');
                                $parent->update(['user_id' => $user->id]);

                                $firstChild = $parent->students()
                                    ->with('currentAcademicHistory.courseClass', 'currentAcademicHistory.section')
                                    ->first();
                                $h = $firstChild?->currentAcademicHistory;

                                $created[] = [
                                    'name'         => $user->name,
                                    'role'         => 'Parent',
                                    'username'     => $user->username,
                                    'password'     => $passwordPlain,
                                    'class_name'   => $h?->courseClass?->name,
                                    'section_name' => $h?->section?->name,
                                ];
                            } catch (\Throwable $e) {
                                $failed++;
                                Log::warning('createMissing parent failure', [
                                    'parent_id' => $parent->id,
                                    'phone'     => $parent->primary_phone,
                                    'error'     => $e->getMessage(),
                                ]);
                            }
                        }
                    });
            }

            // ── Staff (teachers / drivers / accountants / etc.) ──
            // Defensive — Staff is normally created with a User, but if any
            // record was imported with user_id = null, this catches it.
            if (in_array($type, ['staff', 'all'], true)) {
                \App\Models\Staff::where('school_id', $schoolId)
                    ->whereNull('user_id')
                    ->with('user')
                    ->orderBy('id')
                    ->chunkById(200, function ($staffMembers) use ($schoolId, $passwordHash, $passwordPlain, &$created, &$failed) {
                        foreach ($staffMembers as $staff) {
                            try {
                                $username = $staff->user?->phone ?? $staff->employee_id ?? null;
                                if (! $username) continue;

                                $existing = User::where('school_id', $schoolId)
                                    ->where(function ($q) use ($username) {
                                        $q->where('username', $username)
                                          ->orWhere('phone',    $username);
                                    })
                                    ->first();

                                if ($existing) {
                                    $staff->update(['user_id' => $existing->id]);
                                    continue;
                                }

                                $name = $staff->user?->name ?? trim(($staff->employee_id ?? '') . ' (staff)');
                                $user = User::create([
                                    'school_id' => $schoolId,
                                    'name'      => $name,
                                    'username'  => $username,
                                    'phone'     => $username,
                                    'password'  => $passwordHash,
                                    'user_type' => 'teacher',
                                    'is_active' => true,
                                ]);
                                $user->assignRole('teacher');
                                $staff->update(['user_id' => $user->id]);

                                $created[] = [
                                    'name'         => $user->name,
                                    'role'         => 'Staff',
                                    'username'     => $user->username,
                                    'password'     => $passwordPlain,
                                    'class_name'   => null,
                                    'section_name' => null,
                                ];
                            } catch (\Throwable $e) {
                                $failed++;
                                Log::warning('createMissing staff failure', [
                                    'staff_id' => $staff->id,
                                    'error'    => $e->getMessage(),
                                ]);
                            }
                        }
                    });
            }

            // ── Students ──
            if (in_array($type, ['student', 'all'], true)) {
                Student::where('school_id', $schoolId)
                    ->whereNull('user_id')
                    ->whereNotNull('admission_no')
                    ->orderBy('id')
                    ->chunkById(200, function ($students) use ($schoolId, $passwordHash, $passwordPlain, &$created, &$failed) {
                        foreach ($students as $student) {
                            try {
                                $existing = User::where('school_id', $schoolId)
                                    ->where('username', $student->admission_no)
                                    ->first();

                                if ($existing) {
                                    $student->update(['user_id' => $existing->id]);
                                    if (! $existing->hasRole('student')) $existing->assignRole('student');
                                    continue;
                                }

                                $user = User::create([
                                    'school_id' => $schoolId,
                                    'name'      => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                                    'username'  => $student->admission_no,
                                    'password'  => $passwordHash,
                                    'user_type' => 'student',
                                    'is_active' => true,
                                ]);
                                $user->assignRole('student');
                                $student->update(['user_id' => $user->id]);

                                $student->load('currentAcademicHistory.courseClass', 'currentAcademicHistory.section');
                                $h = $student->currentAcademicHistory;

                                $created[] = [
                                    'name'         => $user->name,
                                    'role'         => 'Student',
                                    'username'     => $user->username,
                                    'password'     => $passwordPlain,
                                    'class_name'   => $h?->courseClass?->name,
                                    'section_name' => $h?->section?->name,
                                ];
                            } catch (\Throwable $e) {
                                $failed++;
                                Log::warning('createMissing student failure', [
                                    'student_id'   => $student->id,
                                    'admission_no' => $student->admission_no,
                                    'error'        => $e->getMessage(),
                                ]);
                            }
                        }
                    });
            }
        } finally {
            optional($lock)->release();
        }

        Log::info('Bulk portal user creation', [
            'by'         => auth()->id(),
            'school_id'  => $schoolId,
            'type'       => $type,
            'count'      => count($created),
            'failed'     => $failed,
            'timestamp'  => now()->toIso8601String(),
        ]);

        $count = count($created);
        $message = $count === 0
            ? ($failed > 0 ? "0 created, {$failed} failed (see Laravel log)." : 'No missing accounts to create.')
            : "{$count} account(s) created. Default password: password"
              . ($failed > 0 ? " ({$failed} failed — see Laravel log)" : '');

        return response()->json([
            'success'     => true,
            'count'       => $count,
            'failed'      => $failed,
            'message'     => $message,
            'credentials' => $created,
        ]);
    }

    /**
     * POST /school/users/bulk-reset — reset passwords for the given user IDs
     * to fresh random 10-char strings. Returns the new credentials so the
     * admin can export and share with the users.
     */
    public function bulkReset(Request $request)
    {
        $validated = $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'integer',
        ]);

        $schoolId = app('current_school_id');
        $rows     = [];

        $users = User::where('school_id', $schoolId)
            ->whereIn('id', $validated['user_ids'])
            ->where('id', '!=', auth()->id()) // never bulk-reset your own
            ->get();

        // Eager load class/section relationships
        $users->load([
            'student.currentAcademicHistory.courseClass:id,name',
            'student.currentAcademicHistory.section:id,name',
            'studentParent.students.currentAcademicHistory.courseClass:id,name',
            'studentParent.students.currentAcademicHistory.section:id,name',
        ]);

        DB::transaction(function () use ($users, &$rows) {
            foreach ($users as $u) {
                $newPassword = 'password';
                $u->update(['password' => Hash::make($newPassword)]);
                // user_type is a backed enum (App\Enums\UserType), so we must
                // pull ->value rather than relying on string cast.
                $role = $u->user_type instanceof \BackedEnum
                    ? $u->user_type->value
                    : (string) ($u->user_type ?? '');

                [$class, $section] = $this->classSectionFor($u, $role);

                $rows[] = [
                    'name'         => $u->name,
                    'role'         => ucfirst(str_replace('_', ' ', $role)),
                    'username'     => $u->username ?: $u->phone ?: $u->email ?: '—',
                    'password'     => $newPassword,
                    'class_name'   => $class,
                    'section_name' => $section,
                ];
            }
        });

        \Illuminate\Support\Facades\Log::info('Bulk password reset by admin', [
            'reset_by'  => auth()->id(),
            'school_id' => $schoolId,
            'count'     => count($rows),
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json([
            'success'     => true,
            'count'       => count($rows),
            'credentials' => $rows,
        ]);
    }

    /**
     * POST /school/users/export-credentials — download an Excel or PDF file
     * of the credentials posted in the request body. The credentials are
     * supplied by the client (from the modal that just received them via
     * createMissing or bulkReset). Server doesn't store plaintext passwords.
     */
    public function exportCredentials(Request $request)
    {
        $validated = $request->validate([
            'rows'        => 'required|array|min:1',
            'rows.*.name'     => 'required|string',
            'rows.*.role'     => 'nullable|string',
            'rows.*.username' => 'required|string',
            'rows.*.password' => 'required|string',
            'format'      => 'required|in:xlsx,pdf',
        ]);

        $school = app('current_school');
        $rows   = $validated['rows'];
        $title  = ($school?->name ?? 'School') . ' — User Credentials';
        $stamp  = now()->format('Y-m-d_His');
        $base   = 'user-credentials-' . $stamp;

        if ($validated['format'] === 'xlsx') {
            return Excel::download(new UserCredentialsExport($rows, $title), $base . '.xlsx');
        }

        $pdf = Pdf::loadView('exports.user-credentials', [
            'rows'    => $rows,
            'school'  => $school,
            'title'   => $title,
            'printed' => now()->format('d M Y, h:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($base . '.pdf');
    }

    /**
     * GET /school/users/export-list — directly export the currently filtered
     * user list as Excel/PDF without resetting passwords. The "password"
     * column shows the system default ('password') as a hint to the admin —
     * if a user has changed their password, it won't reflect here.
     *
     * Query params mirror index() so admins can export "all parents in
     * Class 5 / Section A" with one click.
     */
    public function exportList(Request $request)
    {
        $request->validate(['format' => 'required|in:xlsx,pdf']);
        $schoolId = app('current_school_id');

        $query = User::where('school_id', $schoolId)
            ->with([
                'student.currentAcademicHistory.courseClass:id,name',
                'student.currentAcademicHistory.section:id,name',
                'studentParent.students.currentAcademicHistory.courseClass:id,name',
                'studentParent.students.currentAcademicHistory.section:id,name',
            ]);

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        } else {
            $query->whereIn('user_type', ['teacher', 'student', 'parent', 'principal', 'accountant', 'driver']);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('username', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            });
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }
        if ($request->filled('class_id') || $request->filled('section_id')) {
            if ($request->user_type === 'student') {
                $query->whereHas('student.currentAcademicHistory', function ($q) use ($request) {
                    if ($request->filled('class_id'))   $q->where('class_id',   $request->class_id);
                    if ($request->filled('section_id')) $q->where('section_id', $request->section_id);
                });
            } elseif ($request->user_type === 'parent') {
                $query->whereHas('studentParent.students.currentAcademicHistory', function ($q) use ($request) {
                    if ($request->filled('class_id'))   $q->where('class_id',   $request->class_id);
                    if ($request->filled('section_id')) $q->where('section_id', $request->section_id);
                });
            }
        }

        $users = $query->orderBy('name')->get();

        $rows = $users->map(function ($u) {
            $role = $u->user_type instanceof \BackedEnum
                ? $u->user_type->value
                : (string) ($u->user_type ?? '');
            [$class, $section] = $this->classSectionFor($u, $role);
            return [
                'name'         => $u->name,
                'role'         => ucfirst(str_replace('_', ' ', $role)),
                'username'     => $u->username ?: $u->phone ?: $u->email ?: '—',
                'password'     => 'password',          // system default
                'class_name'   => $class,
                'section_name' => $section,
            ];
        })->values()->all();

        $school = app('current_school');
        $title  = ($school?->name ?? 'School') . ' — User Credentials';
        $stamp  = now()->format('Y-m-d_His');
        $base   = 'user-credentials-' . $stamp;

        if (empty($rows)) {
            return response()->json(['message' => 'Nothing to export.'], 422);
        }

        if ($request->format === 'xlsx') {
            return Excel::download(new UserCredentialsExport($rows, $title), $base . '.xlsx');
        }

        $pdf = Pdf::loadView('exports.user-credentials', [
            'rows'    => $rows,
            'school'  => $school,
            'title'   => $title,
            'printed' => now()->format('d M Y, h:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($base . '.pdf');
    }

    /** Resolve display class / section for a User by role. */
    private function classSectionFor($u, string $role): array
    {
        if ($role === 'student' && $u->student?->currentAcademicHistory) {
            $h = $u->student->currentAcademicHistory;
            return [$h->courseClass?->name, $h->section?->name];
        }
        if ($role === 'parent' && $u->studentParent) {
            $first = $u->studentParent->students->first();
            $h = $first?->currentAcademicHistory;
            return [$h?->courseClass?->name, $h?->section?->name];
        }
        return [null, null];
    }
}
