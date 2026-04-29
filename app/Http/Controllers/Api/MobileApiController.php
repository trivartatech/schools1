<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AcademicYear;
use App\Models\ExamSchedule;
use App\Models\FeePayment;
use App\Models\Announcement;
use App\Models\StudentAcademicHistory;
use App\Models\TransportStudentAllocation;
use App\Models\TransportVehicleLiveLocation;
use App\Models\Timetable;
use App\Models\Student;
use App\Models\StudentLeave;
use App\Models\LeaveType;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\PostBookmark;
use App\Models\EditRequest;
use App\Models\Holiday;
use App\Models\StudentDiary;
use App\Models\SyllabusTopic;
use App\Models\SyllabusStatus;
use App\Models\ExamMark;
use App\Models\Complaint;
use App\Models\BookList;
use App\Models\Leave;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Services\FeeService;
use App\Services\TeacherScopeService;
use Illuminate\Validation\Rule;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MobileApiController extends Controller
{
    public function __construct(protected FeeService $feeService) {}

    // ── Dashboard ─────────────────────────────────────────────────────────────
    // For parents: returns data for the ACTIVE child (selected via switcher)
    // Header: X-Active-Student-Id (optional — defaults to first child)

    public function dashboard(Request $request): JsonResponse
    {
        $user    = $request->user();
        $school  = app('current_school');
        $yearId  = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $payload = [
            'user'          => $this->userData($user),
            'school'        => $this->schoolData($school),
            'stats'         => $this->stats($user, $yearId, $request),
            'announcements' => $this->recentAnnouncements($school->id, 5),
            'attendance'    => $this->attendanceSummary($user, $yearId, $request),
            // Parents get the full child list for the switcher
            'children'      => $user->isParent() ? $this->childList($user) : null,
        ];

        // Admin-only rich dashboard: trends, breakdowns, top-level financials
        if ($user->isAdmin()) {
            $payload['admin'] = $this->adminDashboardData($school->id, $yearId);
        }

        return response()->json($payload);
    }

    // ── Admin: Student & Teacher Lists ────────────────────────────────────────

    public function studentList(Request $request): JsonResponse
    {
        $user    = $request->user();
        $school  = app('current_school');
        $yearId  = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $search  = $request->input('search', '');
        $classId = $request->input('class_id');
        $secId   = $request->input('section_id');
        $gender  = $request->input('gender');
        $perPage = 20;

        // Inner-join to student_academic_histories for the CURRENT year only.
        // Without the year filter the join returned one row per academic
        // history record, so a student enrolled for 4 years showed up 4 times
        // in search results. The inner join also drops students who aren't
        // enrolled this year (graduated / transferred out), which is what the
        // admin "Students" screen actually wants.
        $query = Student::where('students.school_id', $school->id)
            ->select('students.id', 'students.first_name', 'students.last_name',
                'students.admission_no', 'students.roll_no', 'students.gender',
                'students.status', 'students.photo',
                'cc.name as class_name', 'sec.name as section_name',
                'sah.class_id', 'sah.section_id', 'u.phone')
            ->join('student_academic_histories as sah', function ($j) use ($yearId) {
                $j->on('sah.student_id', '=', 'students.id');
                if ($yearId) $j->where('sah.academic_year_id', '=', $yearId);
            })
            ->leftJoin('course_classes as cc', 'cc.id', '=', 'sah.class_id')
            ->leftJoin('sections as sec', 'sec.id', '=', 'sah.section_id')
            ->leftJoin('users as u', 'u.id', '=', 'students.user_id');

        // Teachers only see students from their assigned classes/sections
        if ($user->isTeacher()) {
            $scope = app(TeacherScopeService::class)->for($user);
            if ($scope->restricted) {
                if ($scope->sectionIds->isEmpty()) {
                    return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
                }
                $query->whereIn('sah.section_id', $scope->sectionIds);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('students.first_name', 'like', "%{$search}%")
                  ->orWhere('students.last_name',  'like', "%{$search}%")
                  ->orWhere('students.admission_no', 'like', "%{$search}%");
            });
        }
        if ($classId) {
            $query->where('sah.class_id', $classId);
        }
        if ($secId) {
            $query->where('sah.section_id', $secId);
        }
        if ($gender) {
            $query->where('students.gender', $gender);
        }

        $students = $query->orderBy('students.first_name')->paginate($perPage);

        return response()->json([
            'data' => $students->map(function ($s) {
                return [
                    'id'               => $s->id,
                    'name'             => trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? '')),
                    'admission_number' => $s->admission_no,
                    'roll_number'      => $s->roll_no,
                    'class_name'       => $s->class_name,
                    'section_name'     => $s->section_name,
                    'gender'           => $s->gender,
                    'status'           => $s->status,
                    'phone'            => $s->phone,
                ];
            }),
            'total'        => $students->total(),
            'current_page' => $students->currentPage(),
            'last_page'    => $students->lastPage(),
        ]);
    }

    public function studentDetail(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');
        $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $student = Student::where('school_id', $school->id)
            ->with([
                'studentParent',
                'user:id,name,email,phone',
                'documents',
                'transportAllocation.route',
                'transportAllocation.stop',
                'transportAllocation.vehicle',
            ])
            ->findOrFail($id);

        // Academic history — latest record
        $history = \DB::table('student_academic_histories as sah')
            ->leftJoin('course_classes as cc', 'cc.id', '=', 'sah.class_id')
            ->leftJoin('sections as sec', 'sec.id', '=', 'sah.section_id')
            ->where('sah.student_id', $id)
            ->orderByDesc('sah.id')
            ->select('cc.name as class_name', 'sec.name as section_name', 'sah.class_id', 'sah.section_id')
            ->first();

        // Teachers: verify the student belongs to one of their assigned sections
        if ($user->isTeacher()) {
            $scope = app(TeacherScopeService::class)->for($user);
            if ($scope->restricted && $history) {
                if (! $scope->sectionIds->contains($history->section_id)) {
                    return response()->json(['message' => 'You are not authorized to view this student.'], 403);
                }
            } elseif ($scope->restricted && ! $history) {
                return response()->json(['message' => 'You are not authorized to view this student.'], 403);
            }
        }

        // Attendance summary (current academic year)
        $attTotal   = 0;
        $attPresent = 0;
        if ($yearId) {
            $attTotal   = Attendance::where('student_id', $id)->where('academic_year_id', $yearId)->count();
            $attPresent = Attendance::where('student_id', $id)->where('academic_year_id', $yearId)
                ->whereIn('status', ['present', 'late'])->count();
        }

        // Fee summary
        $feeSummary = [];
        try {
            $feeSummary = $this->feeService->getStudentFeeSummary($student, $yearId, $school->id);
        } catch (\Throwable $e) {}

        // Payment history (completed / partial, not cancelled)
        $feePayments = FeePayment::where('school_id', $school->id)
            ->where('student_id', $id)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->where('status', '!=', 'cancelled')
            ->with(['feeHead:id,name'])
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($p) {
                $mode = $p->payment_mode instanceof \BackedEnum
                    ? $p->payment_mode->value
                    : $p->payment_mode;
                $status = $p->status instanceof \BackedEnum
                    ? $p->status->value
                    : $p->status;
                return [
                    'id'           => $p->id,
                    'receipt_no'   => $p->receipt_no,
                    'fee_head'     => $p->feeHead?->name ?? 'Other',
                    'term'         => $p->term,
                    'amount_paid'  => (float) $p->amount_paid,
                    'balance'      => (float) ($p->balance ?? 0),
                    'payment_date' => $p->payment_date?->toDateString(),
                    'payment_mode' => $mode,
                    'status'       => strtolower((string) ($status ?? 'pending')),
                    'has_receipt'  => !empty($p->receipt_no),
                ];
            });

        $parent = $student->studentParent;

        // Exam marks — aggregated per subject per exam schedule (mirrors the
        // web StudentController::show() logic so the mobile Exams tab shows
        // one row per subject with summed marks, percentage, and grade —
        // not one row per raw assessment item).
        $examMarks = [];
        if ($yearId) {
            $marks = \App\Models\ExamMark::where('student_id', $id)
                ->where('academic_year_id', $yearId)
                ->with([
                    'examScheduleSubject.examSchedule.examType',
                    'examScheduleSubject.subject',
                    'examScheduleSubject.markConfigs',
                    'examScheduleSubject.gradingSystem.grades',
                    'examScheduleSubject.examSchedule.scholasticGradingSystem.grades',
                    'assessmentItem',
                ])
                ->get();

            // Default fallback grading system (Scholastic) for this school
            $defaultGrades = \App\Models\GradingSystem::with('grades')
                ->where('school_id', $school->id)
                ->where('type', 'scholastic')
                ->first()
                ?->grades ?? collect();

            $groupedBySchedule = $marks->groupBy(fn($m) => $m->examScheduleSubject->exam_schedule_id);

            foreach ($groupedBySchedule as $scheduleId => $markItems) {
                $schedule = $markItems->first()?->examScheduleSubject?->examSchedule;
                $examName = $schedule?->examType?->name ?? 'Examination';

                $subjectMarks = $markItems->groupBy('examScheduleSubject.subject_id');
                $subjects = [];

                foreach ($subjectMarks as $subjectId => $items) {
                    $ss          = $items->first()?->examScheduleSubject;
                    $subjectName = $ss?->subject?->name ?? 'Unknown';

                    $totalObtained = 0;
                    $totalMax      = 0;
                    $isAbsent      = false;

                    foreach ($items as $item) {
                        if ($item->is_absent) {
                            $isAbsent = true;
                        }
                        $totalObtained += (float) $item->marks_obtained;

                        // Max marks live on the per-item mark config, not on the parent
                        $config = $ss?->markConfigs?->firstWhere('exam_assessment_item_id', $item->exam_assessment_item_id);
                        $totalMax += (float) ($config->max_marks ?? 0);
                    }

                    $percentage = $totalMax > 0 ? ($totalObtained / $totalMax) * 100 : 0;

                    // Grading hierarchy: Subject-specific -> Schedule Scholastic -> School Scholastic
                    $grades = $ss?->gradingSystem?->grades
                        ?? $schedule?->scholasticGradingSystem?->grades
                        ?? $defaultGrades;

                    $matchedGrade = $grades->sortByDesc('min_percentage')
                        ->first(fn($g) => (float) $percentage >= (float) $g->min_percentage);

                    $subjects[] = [
                        'name'       => $subjectName,
                        'obtained'   => $totalObtained,
                        'max'        => $totalMax,
                        'percentage' => round($percentage, 2),
                        'grade'      => $isAbsent ? 'ABS' : ($matchedGrade->name ?? '—'),
                        'is_absent'  => $isAbsent,
                    ];
                }

                $examMarks[] = [
                    'id'        => $scheduleId,
                    'exam_name' => $examName,
                    'subjects'  => $subjects,
                ];
            }
        }

        // Documents
        $documents = $student->documents->map(fn($d) => [
            'document_type'          => $d->document_type,
            'title'                  => $d->title,
            'is_original_submitted'  => (bool) $d->is_original_submitted,
            'original_file_location' => $d->original_file_location,
            'file_url'               => $this->publicFileUrl($d->file_path),
        ])->values();

        // Transport
        $alloc     = $student->transportAllocation;
        $transport = $alloc ? [
            'route_name'      => $alloc->route?->route_name,
            'route_code'      => $alloc->route?->route_code,
            'start_location'  => $alloc->route?->start_location,
            'end_location'    => $alloc->route?->end_location,
            'stop_name'       => $alloc->stop?->stop_name,
            'pickup_time'     => $alloc->stop?->pickup_time,
            'drop_time'       => $alloc->stop?->drop_time,
            'vehicle_number'  => $alloc->vehicle?->vehicle_number,
            'vehicle_name'    => $alloc->vehicle?->vehicle_name,
            'transport_fee'   => $alloc->transport_fee,
            'pickup_type'     => $alloc->pickup_type,
            'start_date'      => $alloc->start_date,
            'status'          => $alloc->status,
        ] : null;

        // Teachers do not see sensitive PII (Aadhaar, religion, caste, category)
        $isTeacher = $user->isTeacher();

        return response()->json([
            'student' => [
                'id'                       => $student->id,
                'name'                     => $student->name,
                'first_name'               => $student->first_name,
                'last_name'                => $student->last_name,
                'admission_no'             => $student->admission_no,
                'erp_no'                   => $student->erp_no,
                'roll_no'                  => $student->roll_no,
                'photo_url'                => $student->photo_url ?? null,
                // Personal
                'dob'                      => $student->dob,
                'gender'                   => $student->gender,
                'blood_group'              => $student->blood_group,
                'religion'                 => $isTeacher ? null : $student->religion,
                'caste'                    => $isTeacher ? null : $student->caste,
                'category'                 => $isTeacher ? null : $student->category,
                'aadhaar_no'               => $isTeacher ? null : $student->aadhaar_no,
                'nationality'              => $student->nationality,
                'mother_tongue'            => $student->mother_tongue,
                'birth_place'              => $student->birth_place,
                'admission_date'           => $student->admission_date,
                'status'                   => $student->status instanceof \BackedEnum ? $student->status->value : $student->status,
                // Emergency
                'emergency_contact_name'   => $student->emergency_contact_name,
                'emergency_contact_phone'  => $student->emergency_contact_phone,
                // Contact / Address
                'email'                    => $student->user?->email,
                'phone'                    => $student->user?->phone,
                'address'                  => $student->address,
                'city'                     => $student->city,
                'state'                    => $student->state,
                'pincode'                  => $student->pincode,
                // Academic
                'class_name'               => $history?->class_name,
                'section_name'             => $history?->section_name,
                // Parent / Guardian
                'father_name'              => $parent?->father_name,
                'father_phone'             => $parent?->father_phone,
                'father_occupation'        => $parent?->father_occupation,
                'father_qualification'     => $parent?->father_qualification,
                'mother_name'              => $parent?->mother_name,
                'mother_phone'             => $parent?->mother_phone,
                'mother_occupation'        => $parent?->mother_occupation,
                'mother_qualification'     => $parent?->mother_qualification,
                'guardian_name'            => $parent?->guardian_name,
                'guardian_phone'           => $parent?->guardian_phone,
                'guardian_email'           => $parent?->guardian_email,
                'primary_phone'            => $parent?->primary_phone,
                'parent_address'           => $parent?->address,
            ],
            'attendance' => [
                'total'          => $attTotal,
                'present'        => $attPresent,
                'absent'         => $attTotal - $attPresent,
                'attendance_pct' => $attTotal > 0 ? round($attPresent / $attTotal * 100) : 0,
            ],
            'fees' => [
                'total_due' => $feeSummary['total_due'] ?? 0,
                'paid'      => $feeSummary['paid']      ?? 0,
                'balance'   => $feeSummary['balance']   ?? 0,
                'discount'  => $feeSummary['discount']  ?? 0,
                'payments'  => $feePayments,
            ],
            'exam_marks' => $examMarks,
            'documents'  => $documents,
            'transport'  => $transport,
        ]);
    }

    public function classOptions(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $classQuery = \App\Models\CourseClass::where('school_id', $school->id)
            ->orderBy('numeric_value')
            ->orderBy('name');

        // Teachers only see their assigned classes/sections
        $teacherSectionIds = null;
        if ($user->isTeacher()) {
            $scope = app(TeacherScopeService::class)->for($user);
            if ($scope->restricted) {
                if ($scope->classIds->isEmpty()) {
                    return response()->json(['classes' => []]);
                }
                $classQuery->whereIn('id', $scope->classIds);
                $teacherSectionIds = $scope->sectionIds;
            }
        }

        $classes = $classQuery->get(['id', 'name', 'numeric_value'])
            ->map(function ($c) use ($teacherSectionIds) {
                $sectionQuery = \App\Models\Section::where('course_class_id', $c->id)
                    ->forCurrentYear()
                    ->orderBy('sort_order')
                    ->orderBy('name');
                if ($teacherSectionIds !== null) {
                    $sectionQuery->whereIn('id', $teacherSectionIds);
                }
                return [
                    'id'       => $c->id,
                    'name'     => $c->name,
                    'sections' => $sectionQuery->get(['id', 'name', 'sort_order'])
                        ->map(fn($s) => ['id' => $s->id, 'name' => $s->name]),
                ];
            });

        return response()->json(['classes' => $classes]);
    }

    public function teacherList(Request $request): JsonResponse
    {
        $school = app('current_school');
        $search = $request->input('search', '');

        $query = \App\Models\Staff::where('school_id', $school->id)
            ->whereHas('user', fn($q) => $q->where('user_type', 'teacher'))
            ->with(['user:id,name,email,phone,avatar']);

        if ($search) {
            $query->whereHas('user', fn($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $teachers = $query->orderBy('id')->paginate(30);

        return response()->json([
            'data' => $teachers->map(function ($t) {
                return [
                    'id'            => $t->id,
                    'name'          => $t->user?->name,
                    'email'         => $t->user?->email,
                    'phone'         => $t->user?->phone,
                    'employee_code' => $t->employee_code,
                    'designation'   => is_object($t->designation) ? ($t->designation->name ?? null) : $t->designation,
                    'department'    => is_object($t->department)  ? ($t->department->name  ?? null) : $t->department,
                ];
            }),
        ]);
    }

    // ── Child List (parent multi-child) ───────────────────────────────────────

    public function children(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isParent()) {
            return response()->json(['children' => []]);
        }
        return response()->json(['children' => $this->childList($user)]);
    }

    // ── Attendance ────────────────────────────────────────────────────────────

    public function attendance(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');
        $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $month  = $request->input('month', now()->format('Y-m'));

        [$year, $mon] = explode('-', $month);
        $from = Carbon::createFromDate((int)$year, (int)$mon, 1)->startOfMonth();
        $to   = $from->copy()->endOfMonth();

        // Teacher view: own staff_attendances records (populated by punch system / admin marking)
        if ($user->isTeacher() && $user->staff) {
            $records = StaffAttendance::where('school_id', $school->id)
                ->where('staff_id', $user->staff->id)
                ->whereBetween('date', [$from, $to])
                ->with('markedBy:id,name')
                ->orderBy('date')
                ->get(['id', 'date', 'status', 'remarks', 'marked_by']);

            $summary = [
                'present'  => $records->where('status', 'present')->count(),
                'absent'   => $records->where('status', 'absent')->count(),
                'late'     => $records->where('status', 'late')->count(),
                'half_day' => $records->where('status', 'half_day')->count(),
                'leave'    => $records->where('status', 'leave')->count(),
                'holiday'  => $records->where('status', 'holiday')->count(),
                'total'    => $records->count(),
            ];
            // Canonical attendance % formula:
            // (present + late*0.5 + half_day*0.5) / (total - holiday) — holidays are not working days.
            $workingDays = max(0, $summary['total'] - $summary['holiday']);
            $summary['working_days']    = $workingDays;
            $summary['attendance_pct']  = $workingDays > 0
                ? round(($summary['present'] + $summary['late'] * 0.5 + $summary['half_day'] * 0.5) / $workingDays * 100, 1)
                : 0;
            // Surface days that should have been marked but weren't (Option B —
            // doesn't penalise the % calc, just makes the gap visible).
            $expected            = $this->expectedWorkingDays($from, $to, $school->id);
            $summary['expected'] = $expected;
            $summary['unmarked'] = max(0, $expected - $summary['total']);

            $dateFmt = $school->dateFmt();
            return response()->json([
                'summary' => $summary,
                'records' => $records->map(fn($r) => [
                    'date'      => optional($r->date)->format($dateFmt),
                    'status'    => $r->status,
                    'remarks'   => $r->remarks,
                    'marked_by' => $r->markedBy?->name,
                ]),
            ]);
        }

        $studentId = $this->resolveStudentId($user, $request);
        if (!$studentId) {
            return response()->json(['summary' => [], 'records' => []]);
        }

        $records = Attendance::where('student_id', $studentId)
            ->whereBetween('date', [$from, $to])
            ->with('markedBy:id,name')
            ->orderBy('date')
            ->get(['id', 'date', 'status', 'remarks', 'marked_by']);

        $summary = [
            'present'        => $records->where('status', 'present')->count(),
            'absent'         => $records->where('status', 'absent')->count(),
            'late'           => $records->where('status', 'late')->count(),
            'half_day'       => $records->where('status', 'half_day')->count(),
            'leave'          => $records->where('status', 'leave')->count(),
            'holiday'        => $records->where('status', 'holiday')->count(),
            'total'          => $records->count(),
        ];
        // Canonical formula: holidays excluded from denominator (not working days)
        $workingDays = max(0, $summary['total'] - $summary['holiday']);
        $summary['working_days']   = $workingDays;
        $summary['attendance_pct'] = $workingDays > 0
            ? round(($summary['present'] + $summary['late'] * 0.5 + $summary['half_day'] * 0.5) / $workingDays * 100, 1)
            : 100;
        // Days that should have been marked but weren't.
        $expected            = $this->expectedWorkingDays($from, $to, $school->id);
        $summary['expected'] = $expected;
        $summary['unmarked'] = max(0, $expected - $summary['total']);

        $dateFmt = $school->dateFmt();
        return response()->json([
            'summary'    => $summary,
            'records'    => $records->map(fn($r) => [
                // Attendance.date has no Eloquent cast (kept as raw "Y-m-d" so
                // WHERE date = … lookups work), so parse before formatting.
                'date'      => $r->date ? Carbon::parse($r->date)->format($dateFmt) : null,
                'status'    => $r->status,
                'remarks'   => $r->remarks,
                'marked_by' => $r->markedBy?->name,
            ]),
            'student_id' => $studentId,
        ]);
    }

    // ── Fees ──────────────────────────────────────────────────────────────────

    public function fees(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId || !$yearId) {
            return response()->json([
                'total_due' => 0, 'paid' => 0, 'balance' => 0,
                'discount' => 0, 'fine' => 0, 'payments' => [], 'fee_heads' => [],
            ]);
        }

        $student = Student::find($studentId);
        $summary = $student ? $this->feeService->getStudentFeeSummary($student, $yearId, $school->id) : [];

        $payments = FeePayment::where('student_id', $studentId)
            ->where('academic_year_id', $yearId)
            ->with(['feeHead.feeGroup', 'collectedBy:id,name'])
            ->orderByDesc('payment_date')
            ->get();

        return response()->json([
            'total_due'  => $summary['total_due']  ?? 0,
            'paid'       => $summary['paid']        ?? 0,
            'balance'    => $summary['balance']     ?? 0,
            'discount'   => $summary['discount']    ?? 0,
            'fine'       => $summary['fine']        ?? 0,
            'fee_heads'  => $summary['fee_heads']   ?? [],
            'payments'   => $payments,
            'student_id' => $studentId,
        ]);
    }

    public function feeDetail(Request $request, int $id): JsonResponse
    {
        $school  = app('current_school');
        $payment = FeePayment::where('school_id', $school->id)->where('id', $id)
            ->with(['feeHead.feeGroup', 'student', 'collectedBy:id,name', 'academicYear'])
            ->firstOrFail();

        $user      = $request->user();
        $studentId = $this->resolveStudentId($user, $request);
        if ($studentId && $payment->student_id !== $studentId) abort(403);

        return response()->json(['payment' => $payment]);
    }

    // ── Timetable ─────────────────────────────────────────────────────────────

    public function timetable(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $schedule  = [];

        // day_of_week is stored as int (1=Mon..7=Sun); mobile app expects lowercase day names.
        $dayNames = [
            1 => 'monday', 2 => 'tuesday', 3 => 'wednesday',
            4 => 'thursday', 5 => 'friday', 6 => 'saturday', 7 => 'sunday',
        ];

        if ($user->isTeacher() && $user->staff) {
            $items = Timetable::where('school_id', $school->id)
                ->where('staff_id', $user->staff->id)
                ->with(['subject', 'courseClass', 'section', 'period'])
                ->get();

            foreach ($items as $item) {
                $dayKey = $dayNames[$item->day_of_week] ?? null;
                if (!$dayKey) continue;
                $schedule[$dayKey][] = [
                    'period_name'  => $item->period->name        ?? "Period {$item->period_id}",
                    'subject_name' => $item->subject->name       ?? 'Unknown',
                    'class_name'   => $item->courseClass->name   ?? '',
                    'section_name' => $item->section->name       ?? '',
                    'start_time'   => $item->period->start_time  ?? '',
                    'end_time'     => $item->period->end_time    ?? '',
                    'room'         => $item->room                ?? '',
                    'is_break'     => false,
                ];
            }
        } else {
            $studentId = $this->resolveStudentId($user, $request);
            $history   = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
                ->where('academic_year_id', $yearId)->first() : null;

            if ($history) {
                $items = Timetable::where('school_id', $school->id)
                    ->where('course_class_id', $history->class_id)
                    ->where('section_id', $history->section_id)
                    ->with(['subject', 'staff.user:id,name', 'period'])
                    ->get();

                foreach ($items as $item) {
                    $dayKey = $dayNames[$item->day_of_week] ?? null;
                    if (!$dayKey) continue;
                    $schedule[$dayKey][] = [
                        'period_name'  => $item->period->name           ?? "Period {$item->period_id}",
                        'subject_name' => $item->subject->name          ?? 'Unknown',
                        'teacher_name' => $item->staff->user->name      ?? '',
                        'start_time'   => $item->period->start_time     ?? '',
                        'end_time'     => $item->period->end_time       ?? '',
                        'room'         => $item->room                   ?? '',
                        'is_break'     => false,
                    ];
                }
            }
        }

        foreach ($schedule as &$day) {
            usort($day, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));
        }

        return response()->json(['schedule' => $schedule]);
    }

    // ── Transport ─────────────────────────────────────────────────────────────

    /**
     * Live bus status for the current parent's active child (or student themselves).
     * Polled every 15 s by the mobile parent BusTracking screen.
     */
    public function busStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        $studentId = $this->resolveStudentId($user, $request);
        if (!$studentId) {
            return response()->json(['status' => 'idle']);
        }

        $alloc = TransportStudentAllocation::where('student_id', $studentId)
            ->where('status', 'active')
            ->with([
                'vehicle.liveLocation',
                'vehicle.route:id,route_name',
                'vehicle.driver:id,user_id',
                'vehicle.driver.user:id,name',
            ])
            ->first();

        if (!$alloc || !$alloc->vehicle) {
            return response()->json(['status' => 'idle']);
        }

        $vehicle    = $alloc->vehicle;
        $live       = $vehicle->liveLocation;
        $driverName = $vehicle->driver?->user?->name;
        $routeName  = $vehicle->route?->route_name;

        if (!$live) {
            return response()->json([
                'status'      => 'idle',
                'driver_name' => $driverName,
                'route_name'  => $routeName,
            ]);
        }

        // The driver-tracking endpoint stops a trip by setting updated_at to 10 min ago.
        // Treat anything within 5 min as live; 5–30 min as recently stopped; older as idle.
        $minutesAgo = (int) $live->updated_at->diffInMinutes(now());
        if ($minutesAgo <= 5) {
            $status = 'running';
        } elseif ($minutesAgo <= 30) {
            $status = 'stopped';
        } else {
            $status = 'idle';
        }

        return response()->json([
            'status'      => $status,
            'driver_name' => $driverName,
            'route_name'  => $routeName,
            'latitude'    => $live->latitude,
            'longitude'   => $live->longitude,
            'stopped_at'  => $status === 'stopped' ? $live->updated_at?->toIso8601String() : null,
        ]);
    }

    public function transport(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $locations = TransportVehicleLiveLocation::with([
            'vehicle:id,school_id,vehicle_number,vehicle_name,route_id,driver_id,conductor_name,capacity,status',
            'vehicle.route:id,route_name,route_code,start_location,end_location,distance,estimated_time',
            'vehicle.route.stops' => fn($q) => $q->orderBy('stop_order'),
            'vehicle.route.stops.studentAllocations' => fn($q) => $q->where('status', 'active'),
            'vehicle.driver:id,user_id',
            'vehicle.driver.user:id,name,phone',
        ])
        ->whereHas('vehicle', fn($q) => $q->where('school_id', $school->id))
        ->where('updated_at', '>=', now()->subMinutes(5))
        ->get();

        // Enrich each location with computed tracking data
        $locations->transform(function ($loc) {
            $vehicle = $loc->vehicle;
            $route   = $vehicle?->route;
            $stops   = $route?->stops ?? collect();

            $busLat = (float) $loc->latitude;
            $busLng = (float) $loc->longitude;
            $speed  = (float) ($loc->speed ?? 0);

            $nearestIdx  = 0;
            $nearestDist = PHP_FLOAT_MAX;

            $stopsData = $stops->values()->map(function ($stop, $idx) use ($busLat, $busLng, &$nearestIdx, &$nearestDist) {
                $dist = $this->transportHaversine($busLat, $busLng, (float) $stop->latitude, (float) $stop->longitude);
                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearestIdx  = $idx;
                }
                return [
                    'id'                   => $stop->id,
                    'name'                 => $stop->stop_name,
                    'stop_order'           => $stop->stop_order,
                    'pickup_time'          => $stop->pickup_time,
                    'drop_time'            => $stop->drop_time,
                    'latitude'             => $stop->latitude,
                    'longitude'            => $stop->longitude,
                    'student_count'        => $stop->studentAllocations->count(),
                    'distance_from_school' => $stop->distance_from_school,
                ];
            });

            // Mark stops as passed / current / upcoming
            $stopsData = $stopsData->map(function ($s, $idx) use ($nearestIdx) {
                $s['status'] = $idx < $nearestIdx ? 'passed' : ($idx === $nearestIdx ? 'current' : 'upcoming');
                return $s;
            });

            $nextStop     = $stopsData->get($nearestIdx + 1);
            $nextStopDist = $nextStop
                ? $this->transportHaversine($busLat, $busLng, (float) $nextStop['latitude'], (float) $nextStop['longitude'])
                : null;

            $etaMinutes = ($speed > 0 && $nextStopDist !== null)
                ? round(($nextStopDist / $speed) * 60)
                : null;

            $lastStop     = $stopsData->last();
            $distToSchool = $lastStop
                ? round($this->transportHaversine($busLat, $busLng, (float) $lastStop['latitude'], (float) $lastStop['longitude']), 1)
                : null;

            $loc->tracking = [
                'stops'          => $stopsData->values(),
                'nearest_stop'   => $stopsData->get($nearestIdx),
                'next_stop'      => $nextStop,
                'next_stop_dist' => $nextStopDist ? round($nextStopDist, 1) : null,
                'eta_minutes'    => $etaMinutes,
                'dist_to_school' => $distToSchool,
                'driver_name'    => $vehicle?->driver?->user?->name ?? 'N/A',
                'driver_phone'   => $vehicle?->driver?->user?->phone ?? null,
                'conductor_name' => $vehicle?->conductor_name ?? 'N/A',
                'total_students' => $stopsData->sum('student_count'),
            ];

            return $loc;
        });

        $myAllocation = null;
        $studentId    = $this->resolveStudentId($user, $request);
        if ($studentId) {
            $myAllocation = TransportStudentAllocation::where('student_id', $studentId)
                ->where('school_id', $school->id)
                ->where('status', 'active')
                ->with([
                    'route:id,route_name,route_code',
                    'stop:id,stop_name,stop_order,pickup_time,drop_time,latitude,longitude,distance_from_school',
                    'vehicle:id,vehicle_number,vehicle_name',
                ])
                ->first();
        }

        return response()->json([
            'locations'     => $locations,
            'my_allocation' => $myAllocation,
        ]);
    }

    // ── Transport attendance (driver app) ─────────────────────────────────────

    /**
     * GET /mobile/transport/attendance/students
     *
     * Returns the student roster for a route + date + trip type, with each
     * student's current attendance status (if already marked). Drivers see
     * only routes assigned to vehicles they drive.
     */
    public function transportAttendanceStudents(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $routeId   = $request->integer('route_id');
        $date      = $request->input('date', now()->toDateString());
        $tripType  = $request->input('trip_type', 'pickup');

        if (!$routeId) {
            return response()->json(['students' => []]);
        }

        // Drivers can only see routes their vehicles run on
        $role = $user->user_type instanceof \App\Enums\UserType ? $user->user_type->value : (string) $user->user_type;
        if (in_array($role, ['driver', 'conductor'])) {
            $staffId = \App\Models\Staff::where('user_id', $user->id)->where('school_id', $school->id)->value('id');
            $allowed = \App\Models\TransportVehicle::where('school_id', $school->id)
                ->where(function ($q) use ($staffId) {
                    $q->where('driver_id', $staffId)->orWhere('conductor_id', $staffId);
                })
                ->where('route_id', $routeId)
                ->exists();
            if (!$allowed) return response()->json(['students' => []]);
        }

        $allocations = TransportStudentAllocation::where('school_id', $school->id)
            ->where('route_id', $routeId)
            ->where('status', 'active')
            ->whereIn('pickup_type', [$tripType, 'both'])
            ->with([
                'student:id,first_name,last_name,admission_no,user_id',
                'student.user:id,name',
                'stop:id,stop_name,stop_order',
            ])
            ->get();

        $existing = \App\Models\TransportAttendance::where('school_id', $school->id)
            ->where('route_id', $routeId)
            ->where('date', $date)
            ->where('trip_type', $tripType)
            ->get()
            ->keyBy('student_id');

        $students = $allocations->map(function ($a) use ($existing) {
            $att      = $existing->get($a->student_id);
            $student  = $a->student;
            $name     = $student?->user?->name
                     ?? trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? ''));
            return [
                'student_id'   => $a->student_id,
                'name'         => $name ?: '—',
                'admission_no' => $student?->admission_no ?? '—',
                'stop_name'    => $a->stop?->stop_name ?? '—',
                'stop_order'   => $a->stop?->stop_order ?? 0,
                'vehicle_id'   => $a->vehicle_id,
                'status'       => $att?->status,         // null if not yet marked
                'boarded_at'   => $att?->boarded_at,
                'notes'        => $att?->notes,
            ];
        })->sortBy('stop_order')->values();

        return response()->json(['students' => $students]);
    }

    /**
     * POST /mobile/transport/attendance/mark
     *
     * Bulk-saves transport attendance for a route + date + trip. Mirrors the
     * web TransportAttendanceController::store but returns JSON so the mobile
     * app can show a confirmation. Triggers parent push/SMS via NotificationService.
     */
    public function transportAttendanceMark(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $validated = $request->validate([
            'route_id'             => 'required|integer',
            'date'                 => 'required|date',
            'trip_type'            => 'required|in:pickup,drop',
            'records'              => 'required|array|min:1',
            'records.*.student_id' => 'required|integer',
            'records.*.status'     => 'required|in:present,absent,late',
            'records.*.boarded_at' => 'nullable',
            'records.*.notes'      => 'nullable|string|max:255',
        ]);

        // Driver/conductor scope check
        $role = $user->user_type instanceof \App\Enums\UserType ? $user->user_type->value : (string) $user->user_type;
        if (in_array($role, ['driver', 'conductor'])) {
            $staffId = \App\Models\Staff::where('user_id', $user->id)->where('school_id', $school->id)->value('id');
            $allowed = \App\Models\TransportVehicle::where('school_id', $school->id)
                ->where(function ($q) use ($staffId) {
                    $q->where('driver_id', $staffId)->orWhere('conductor_id', $staffId);
                })
                ->where('route_id', $validated['route_id'])
                ->exists();
            if (!$allowed) return response()->json(['message' => 'Not assigned to this route.'], 403);
        }

        $vehicle = \App\Models\TransportVehicle::where('school_id', $school->id)
            ->where('route_id', $validated['route_id'])->first();

        $stopsByStudent = TransportStudentAllocation::where('school_id', $school->id)
            ->where('route_id', $validated['route_id'])
            ->where('status', 'active')
            ->with('stop:id,stop_name')
            ->get()
            ->keyBy('student_id');

        $saved = 0;
        foreach ($validated['records'] as $rec) {
            \App\Models\TransportAttendance::updateOrCreate(
                [
                    'school_id'  => $school->id,
                    'student_id' => $rec['student_id'],
                    'date'       => $validated['date'],
                    'trip_type'  => $validated['trip_type'],
                ],
                [
                    'route_id'   => $validated['route_id'],
                    'vehicle_id' => $vehicle?->id,
                    'status'     => $rec['status'],
                    'boarded_at' => $rec['boarded_at'] ?? null,
                    'notes'      => $rec['notes'] ?? null,
                    'marked_by'  => $user->id,
                ]
            );
            $saved++;
        }

        // Fire parent notifications (non-fatal — failures are logged)
        try {
            $notifier = new \App\Services\NotificationService($school);
            $studentIds = collect($validated['records'])->pluck('student_id');
            $students = \App\Models\Student::whereIn('id', $studentIds)
                ->with(['user:id,name', 'studentParent.user'])
                ->get()->keyBy('id');
            foreach ($validated['records'] as $rec) {
                $student  = $students->get($rec['student_id']);
                $stopName = $stopsByStudent->get($rec['student_id'])?->stop?->stop_name;
                if ($student) {
                    $notifier->notifyTransportAttendance($student, $rec['status'], $validated['trip_type'], $stopName);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Transport attendance push failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'saved' => $saved]);
    }

    // ── Front gate keeper (gate passes + visitor log) ────────────────────────

    /**
     * GET /mobile/front-office/gate/stats
     * Today's counts for the gate keeper dashboard.
     */
    public function gateStats(Request $request): JsonResponse
    {
        $school = app('current_school');
        $today  = now()->toDateString();

        $exitsToday = \App\Models\GatePass::where('school_id', $school->id)
            ->whereDate('exit_time', $today)->count();
        $entriesToday = \App\Models\GatePass::where('school_id', $school->id)
            ->whereDate('return_time', $today)->count();
        $openPasses = \App\Models\GatePass::where('school_id', $school->id)
            ->whereIn('status', ['Approved', 'Exited'])->count();

        $visitorsIn = \App\Models\VisitorLog::where('school_id', $school->id)
            ->whereDate('in_time', $today)
            ->whereNull('out_time')->count();
        $visitorsToday = \App\Models\VisitorLog::where('school_id', $school->id)
            ->whereDate('in_time', $today)->count();

        return response()->json([
            'entries_today'  => $entriesToday,
            'exits_today'    => $exitsToday,
            'visitors_in'    => $visitorsIn,
            'visitors_today' => $visitorsToday,
            'open_passes'    => $openPasses,
        ]);
    }

    /**
     * GET /mobile/front-office/gate-passes/verify/{token}
     * Resolve a gate pass by its QR token. Includes the next valid action
     * (exit / entry) for the current status so the mobile UI doesn't have
     * to encode the state machine.
     */
    public function verifyGatePass(Request $request, string $token): JsonResponse
    {
        $school = app('current_school');

        $pass = \App\Models\GatePass::where('school_id', $school->id)
            ->where('qr_code_token', $token)
            ->with([
                'user',
                'requestedBy',
                'verifiedBy:id,name',
            ])
            ->first();

        if (!$pass) {
            return response()->json(['valid' => false, 'message' => 'Gate pass not found.'], 404);
        }

        // Resolve the holder name from the polymorphic user relationship
        $holderName = $pass->user?->name
            ?? $pass->user?->first_name
            ?? $pass->picked_up_by_name
            ?? '—';

        return response()->json([
            'valid'        => true,
            'id'           => $pass->id,
            'status'       => strtolower($pass->status),                  // pending|approved|exited|returned|rejected
            'pass_type'    => $pass->pass_type,
            'holder_name'  => $holderName,
            'reason'       => $pass->reason,
            'relationship' => $pass->relationship,
            'exit_time'    => optional($pass->exit_time)->toDateTimeString(),
            'return_time'  => optional($pass->return_time)->toDateTimeString(),
            'verified_by'  => $pass->verifiedBy?->name,
            'next_action'  => match ($pass->status) {
                'Approved' => 'exit',     // student is on campus → leaving
                'Exited'   => 'entry',    // student is outside  → returning
                default    => null,        // Pending / Rejected / Returned → no action
            },
        ]);
    }

    /**
     * POST /mobile/front-office/gate-passes/{id}/exit
     * Records exit_time. Status transitions Approved → Exited.
     */
    public function gatePassExit(Request $request, int $id): JsonResponse
    {
        return $this->gatePassTransition($id, 'Exited');
    }

    /**
     * POST /mobile/front-office/gate-passes/{id}/entry
     * Records return_time. Status transitions Exited → Returned.
     */
    public function gatePassEntry(Request $request, int $id): JsonResponse
    {
        return $this->gatePassTransition($id, 'Returned');
    }

    private function gatePassTransition(int $id, string $newStatus): JsonResponse
    {
        $school = app('current_school');
        $pass   = \App\Models\GatePass::where('school_id', $school->id)->find($id);
        if (!$pass) return response()->json(['message' => 'Gate pass not found.'], 404);

        $allowed = match ($pass->status) {
            'Pending'  => ['Approved', 'Rejected'],
            'Approved' => ['Exited', 'Rejected'],
            'Exited'   => ['Returned'],
            default    => [],
        };
        if (!in_array($newStatus, $allowed)) {
            return response()->json([
                'message' => "Cannot change status from {$pass->status} to {$newStatus}.",
            ], 422);
        }

        $pass->status = $newStatus;
        if ($newStatus === 'Exited')   $pass->exit_time   = now();
        if ($newStatus === 'Returned') $pass->return_time = now();
        $pass->save();

        return response()->json([
            'success' => true,
            'status'  => strtolower($newStatus),
        ]);
    }

    /**
     * GET /mobile/front-office/visitors
     * List visitor logs. ?today=1 restricts to today only.
     * Default sort: most recent first (newest in_time on top).
     */
    public function visitorList(Request $request): JsonResponse
    {
        $school = app('current_school');

        $query = \App\Models\VisitorLog::where('school_id', $school->id);

        if ($request->boolean('today')) {
            $today = now()->toDateString();
            $query->whereDate('in_time', $today);
        }

        $visitors = $query->orderByDesc('in_time')
            ->limit(100)
            ->get(['id', 'name', 'phone', 'purpose', 'in_time', 'out_time', 'badge_number', 'photo_path'])
            ->map(fn ($v) => [
                'id'           => $v->id,
                'name'         => $v->name,
                'phone'        => $v->phone,
                'purpose'      => $v->purpose,
                'badge_number' => $v->badge_number,
                'entry_time'   => optional($v->in_time)->format('H:i'),
                'exit_time'    => optional($v->out_time)->format('H:i'),
                'in_time'      => optional($v->in_time)->toDateTimeString(),
                'out_time'     => optional($v->out_time)->toDateTimeString(),
            ])
            ->values();

        return response()->json([
            'data'  => $visitors,
            'total' => $visitors->count(),
        ]);
    }

    /**
     * POST /mobile/front-office/visitors
     * Log a walk-in visitor (in_time = now).
     */
    public function logVisitor(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'phone'    => 'nullable|string|max:20',
            'purpose'  => 'required|in:Meeting,Admission,Delivery,Other',
            'notes'    => 'nullable|string|max:1000',
            'badge_number' => 'nullable|string|max:50',
        ]);

        $visitor = new \App\Models\VisitorLog($validated);
        $visitor->school_id = $school->id;
        $visitor->academic_year_id = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $visitor->in_time = now();
        $visitor->save();

        return response()->json([
            'success' => true,
            'id'      => $visitor->id,
            'message' => 'Visitor logged.',
        ], 201);
    }

    /**
     * POST /mobile/front-office/visitors/{id}/exit
     * Sets out_time = now.
     */
    public function visitorExit(Request $request, int $id): JsonResponse
    {
        $school  = app('current_school');
        $visitor = \App\Models\VisitorLog::where('school_id', $school->id)->find($id);
        if (!$visitor) return response()->json(['message' => 'Visitor not found.'], 404);
        if ($visitor->out_time) return response()->json(['message' => 'Visitor already exited.'], 422);

        $visitor->update(['out_time' => now()]);
        return response()->json(['success' => true, 'out_time' => $visitor->out_time->toDateTimeString()]);
    }

    // ── Announcements ─────────────────────────────────────────────────────────

    public function announcements(Request $request): JsonResponse
    {
        $school = app('current_school');
        $user   = $request->user();
        $page   = $request->integer('page', 1);

        $query = Announcement::where('school_id', $school->id)
            ->with(['sender:id,name', 'template:id,name'])
            ->orderByDesc('created_at');

        // Non-admins only see successfully broadcasted announcements
        $isAdmin = in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin']);
        if (!$isAdmin) {
            $query->where('is_broadcasted', true)->whereNull('failed_at');
        }

        if ($request->filled('type')) {
            $query->where('delivery_method', $request->type);
        }
        if ($request->filled('status')) {
            match ($request->status) {
                'sent'    => $query->where('is_broadcasted', true)->whereNull('failed_at'),
                'failed'  => $query->whereNotNull('failed_at'),
                'pending' => $query->where('is_broadcasted', false)->whereNull('failed_at'),
                default   => null,
            };
        }

        $paginated = $query->paginate(20, ['*'], 'page', $page);

        $paginated->getCollection()->transform(function ($a) {
            $status = 'pending';
            if ($a->failed_at)      $status = 'failed';
            elseif ($a->is_broadcasted) $status = 'sent';

            $audienceLabel = match ($a->audience_type) {
                'school'   => 'All Students & Parents',
                'employee' => 'All Employees',
                default    => (count($a->audience_ids ?? []) > 0
                    ? count($a->audience_ids) . ' ' . $a->audience_type . '(s)'
                    : ucfirst($a->audience_type ?? 'School')),
            };

            return [
                'id'              => $a->id,
                'title'           => $a->title,
                'delivery_method' => $a->delivery_method,
                'audience_type'   => $a->audience_type,
                'audience_label'  => $audienceLabel,
                'audience_ids'    => $a->audience_ids,
                'template_name'   => $a->template?->name,
                'has_audio'       => !empty($a->audio_path) || !empty($a->mp3_path),
                'audio_url'       => $this->publicFileUrl($a->mp3_path ?: $a->audio_path),
                'is_broadcasted'  => $a->is_broadcasted,
                'status'          => $status,
                'broadcast_error' => $a->broadcast_error ? ($a->broadcast_error['message'] ?? 'Unknown error') : null,
                'scheduled_at'    => $a->scheduled_at?->toIso8601String(),
                'failed_at'       => $a->failed_at?->toIso8601String(),
                'created_at'      => $a->created_at->toIso8601String(),
                'sender'          => $a->sender ? ['name' => $a->sender->name] : null,
            ];
        });

        return response()->json($paginated);
    }

    public function templateOptions(Request $request): JsonResponse
    {
        $school = app('current_school');
        $type   = $request->input('type'); // voice | sms | whatsapp

        $query = \App\Models\CommunicationTemplate::where('school_id', $school->id)
            ->where('is_active', true);

        if ($type) {
            $query->where('type', $type);
        } else {
            $query->whereIn('type', ['voice', 'sms', 'whatsapp']);
        }

        $templates = $query->orderBy('name')->get(['id', 'type', 'name', 'content']);

        return response()->json(['templates' => $templates]);
    }

    public function storeAnnouncement(Request $request): JsonResponse
    {
        $school = app('current_school');
        $user   = $request->user();

        if (!in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'title'                     => 'required|string|max:255',
            'delivery_method'           => 'required|in:voice,sms,whatsapp',
            'audience_type'             => 'required|in:school,class,section,employee,individual',
            'audience_ids'              => 'nullable|array',
            'communication_template_id' => 'nullable|exists:communication_templates,id',
            'scheduled_at'              => 'nullable|date|after:now',
            'audio'                     => 'nullable|file|mimes:mp3,wav,ogg,m4a,aac,webm,mp4|max:10240',
        ]);

        $audioPath = null;
        if ($request->hasFile('audio') && $request->file('audio')->isValid()) {
            $audioPath = $request->file('audio')->store('announcements', 'public');
        }

        $announcement = Announcement::create([
            'school_id'                 => $school->id,
            'sender_id'                 => $user->id,
            'title'                     => $request->title,
            'delivery_method'           => $request->delivery_method,
            'audience_type'             => $request->audience_type,
            'audience_ids'              => $request->audience_ids ?? [],
            'communication_template_id' => $request->communication_template_id,
            'audio_path'                => $audioPath,
            'scheduled_at'              => $request->scheduled_at,
            'is_broadcasted'            => false,
        ]);

        return response()->json([
            'message'      => $announcement->scheduled_at
                ? 'Announcement scheduled.'
                : 'Announcement saved as draft.',
            'announcement' => ['id' => $announcement->id, 'title' => $announcement->title],
        ], 201);
    }

    public function broadcastAnnouncement(Request $request, int $id): JsonResponse
    {
        $school = app('current_school');
        $user   = $request->user();

        if (!in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $announcement = Announcement::where('school_id', $school->id)->findOrFail($id);

        if ($announcement->is_broadcasted) {
            return response()->json(['message' => 'Already broadcasted.'], 422);
        }

        try {
            app(\App\Services\BroadcastService::class)->broadcast($announcement);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Broadcast failed: ' . $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Broadcast started.']);
    }

    // ── Exams ─────────────────────────────────────────────────────────────────

    public function exams(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');
        $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $role = $user->user_type instanceof \App\Enums\UserType ? $user->user_type->value : (string) $user->user_type;
        $isStaff = in_array($role, ['admin', 'super_admin', 'school_admin', 'principal', 'teacher']);

        $query = ExamSchedule::where('school_id', $school->id)
            ->where('academic_year_id', $yearId)
            ->with([
                'examType:id,name,code',
                'courseClass:id,name',
                'sections:id,name',
                'scheduleSubjects' => fn($q) => $q->where('is_enabled', true)->orderBy('exam_date')->orderBy('exam_time'),
                'scheduleSubjects.subject:id,name',
            ]);

        if ($isStaff && $role === 'teacher') {
            // Teachers see schedules for classes they're assigned to (incharge or timetable)
            $scope = app(\App\Services\TeacherScopeService::class)->for($user);
            if ($scope->restricted) {
                $classIds = array_keys($scope->allowedMap ?? []);
                $query->whereIn('course_class_id', $classIds ?: [0]);
            }
            // Teachers see drafts too (their own work) — but only published from other teachers
            // Keeping it simple: teachers see only published, same as parents/students. Admins see everything.
            $query->where('status', 'published');
        } elseif ($isStaff) {
            // Admin / school admin / principal — see everything (drafts included so they can review)
            // No additional filter
        } else {
            // Parent / student — scope to their child's class + published only
            $studentId = $this->resolveStudentId($user, $request);
            $history   = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
                ->where('academic_year_id', $yearId)->first() : null;
            if (!$history) {
                return response()->json(['schedules' => []]);
            }
            $query->where('course_class_id', $history->class_id)
                  ->where('status', 'published');
        }

        $schedules = $query->orderByDesc('created_at')->get();

        // Flatten the response — mobile UIs work better with denormalised rows.
        // Each row is one subject paper with everything the screen needs.
        $rows = $schedules->flatMap(function ($schedule) {
            return $schedule->scheduleSubjects->map(function ($paper) use ($schedule) {
                return [
                    'id'                 => $paper->id,
                    'schedule_id'        => $schedule->id,
                    'exam_name'          => $schedule->examType?->name,
                    'exam_code'          => $schedule->examType?->code,
                    'class_name'         => $schedule->courseClass?->name,
                    'section_names'      => $schedule->sections->pluck('name')->implode(', '),
                    'subject_id'         => $paper->subject_id,
                    'subject_name'       => $paper->subject?->name,
                    'exam_date'          => $paper->exam_date ? \Carbon\Carbon::parse($paper->exam_date)->format('Y-m-d') : null,
                    'exam_time'          => $paper->exam_time,                       // "HH:MM:SS" string
                    'duration_minutes'   => $paper->duration_minutes,
                    'writing_marks'      => $paper->writing_marks,
                    'passing_marks'      => $paper->passing_marks,
                    'is_co_scholastic'   => (bool) $paper->is_co_scholastic,
                    'status'             => $schedule->status,
                ];
            });
        })->values();

        return response()->json([
            'schedules' => $schedules, // legacy shape, keep for older clients
            'papers'    => $rows,      // new flattened shape used by ExamScheduleScreen
        ]);
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

    // ── Notifications ─────────────────────────────────────────────────────────

    public function notifications(Request $request): JsonResponse
    {
        $user      = $request->user();
        $paginated = $user->notifications()->latest()->paginate(30);
        return response()->json([
            'notifications' => $paginated->items(),
            'unread_count'  => $user->unreadNotifications()->count(),
            'total'         => $paginated->total(),
        ]);
    }

    public function markNotificationRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->where('id', $id)->first()?->markAsRead();
        return response()->json(['success' => true]);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    }

    // ── Device Registration ───────────────────────────────────────────────────

    public function registerDevice(Request $request): JsonResponse
    {
        // Accept either an Expo push token (preferred) or a raw FCM token.
        // The mobile app uses Expo Push; FCM stays supported for any future
        // client that doesn't go through Expo's relay.
        $validated = $request->validate([
            'expo_push_token' => 'nullable|string',
            'fcm_token'       => 'nullable|string',
            'device_type'     => 'nullable|in:mobile,tablet',
            'platform'        => 'nullable|in:android,ios,web',
        ]);

        if (empty($validated['expo_push_token']) && empty($validated['fcm_token'])) {
            return response()->json([
                'message' => 'Either expo_push_token or fcm_token is required.',
            ], 422);
        }

        $update = ['push_token_updated_at' => now()];
        if (!empty($validated['expo_push_token'])) $update['expo_push_token'] = $validated['expo_push_token'];
        if (!empty($validated['fcm_token']))       $update['fcm_token']       = $validated['fcm_token'];
        if (!empty($validated['platform']))        $update['device_platform'] = $validated['platform'];

        $request->user()->update($update);
        return response()->json(['success' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Count working days between two dates (inclusive), capped at today, with
     * Sundays and declared school holidays subtracted. Used to surface an
     * "unmarked" gap on attendance summaries — days that should have had a
     * record but don't. Doesn't penalise the % calculation; just makes the
     * gap visible (Option B from the audit).
     *
     * @param  \Carbon\Carbon  $from
     * @param  \Carbon\Carbon  $to
     * @param  int             $schoolId
     */
    private function expectedWorkingDays($from, $to, int $schoolId): int
    {
        $today = now()->startOfDay();
        $end   = $to->copy()->lte($today) ? $to->copy() : $today;
        if ($end->lt($from)) return 0;

        $workingDays = 0;
        for ($d = $from->copy(); $d->lte($end); $d->addDay()) {
            // Treat Sunday as the only weekend by default.
            if (!$d->isSunday()) $workingDays++;
        }

        $holidayCount = Holiday::where('school_id', $schoolId)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->count();

        return max(0, $workingDays - $holidayCount);
    }

    /**
     * Resolve which student's data to serve.
     * For parents with multiple children, honour the X-Active-Student-Id header
     * or `student_id` query param. Always validates ownership.
     */
    private function resolveStudentId($user, ?Request $request = null): ?int
    {
        if ($user->isStudent()) {
            return $user->student?->id;
        }

        if ($user->isParent()) {
            $parent   = $user->studentParent;
            if (!$parent) return null;

            $children = $parent->students()->pluck('id');
            if ($children->isEmpty()) return null;

            // Check for explicit child selection
            $requested = $request?->header('X-Active-Student-Id')
                      ?? $request?->input('student_id');

            if ($requested && $children->contains((int)$requested)) {
                return (int)$requested;
            }

            // Default: first child
            return $children->first();
        }

        return null;
    }

    private function childList($user): array
    {
        $parent = $user->studentParent;
        if (!$parent) return [];

        $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        return $parent->students()
            ->with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->get()
            ->map(function ($s) use ($yearId) {
                // Per-child attendance % for the current academic year
                $attPct = null;
                if ($yearId) {
                    $tot  = Attendance::where('student_id', $s->id)->where('academic_year_id', $yearId)->count();
                    $pres = $tot > 0 ? Attendance::where('student_id', $s->id)->where('academic_year_id', $yearId)
                        ->whereIn('status', ['present', 'late'])->count() : 0;
                    $attPct = $tot > 0 ? round($pres / $tot * 100) : null;
                }

                return [
                    'id'               => $s->id,
                    'name'             => $s->name,
                    'admission_number' => $s->admission_no,
                    'roll_number'      => $s->roll_no,
                    'photo_url'        => $s->photo_url,
                    'class_name'       => $s->currentAcademicHistory?->courseClass?->name ?? '',
                    'section_name'     => $s->currentAcademicHistory?->section?->name ?? '',
                    'attendance_pct'   => $attPct,
                ];
            })
            ->toArray();
    }

    /**
     * Normalize a stored file path. Upload code in different modules has
     * saved paths in three different shapes over time:
     *   - "academic/diary/Deni.jpg"                 (bare — cleanest)
     *   - "storage/academic/diary/Deni.jpg"         (web-prefixed)
     *   - "public/academic/diary/Deni.jpg"          (disk-prefixed)
     * We strip any of these so the remainder is always relative to
     * storage/app/public.
     */
    private function normalizeStoragePath(string $path): string
    {
        $p = ltrim($path, '/');
        $p = preg_replace('#^(?:storage/|public/)+#i', '', $p);
        return $p;
    }

    /**
     * Build a public URL for a file in storage/app/public.
     *
     * Routed through /api/media?p=<path>. The path lives in the query
     * string (not the URL path) so nginx's image-extension location
     * regex can't intercept it before the request reaches Laravel.
     */
    private function publicFileUrl(?string $path): ?string
    {
        if (!$path || !is_string($path)) return null;
        if (preg_match('#^https?://#i', $path)) return $path;
        $clean = $this->normalizeStoragePath($path);
        return url('api/media') . '?p=' . rawurlencode($clean);
    }

    /**
     * Convert an array of attachment storage paths into absolute public URLs.
     * - Keeps already-absolute URLs (http/https) untouched.
     * - Routes relative paths through /api/media?p= (Laravel-served) so mobile
     *   clients don't depend on the /storage nginx symlink being readable.
     * - Tolerates arrays that are accidentally JSON-encoded strings.
     */
    private function absolutizeAttachments($attachments): array
    {
        if (is_string($attachments)) {
            $decoded = json_decode($attachments, true);
            $attachments = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($attachments)) return [];

        $out = [];
        foreach ($attachments as $path) {
            if (!is_string($path) || $path === '') continue;
            if (preg_match('#^https?://#i', $path)) {
                $out[] = $path;
            } else {
                $clean = $this->normalizeStoragePath($path);
                $out[] = url('api/media') . '?p=' . rawurlencode($clean);
            }
        }
        return $out;
    }

    /**
     * GET /api/media?p=<relative-path>
     * Streams a file from storage/app/public through Laravel so we don't
     * depend on nginx being able to follow the /storage symlink, and so
     * nginx's image-extension location regex can't intercept the URL.
     * Public because filenames are random hashes; still guards against
     * path traversal and symlink escape.
     */
    public function serveFile(Request $request)
    {
        // Laravel has already URL-decoded query params; strip any historical
        // storage/ or public/ prefix.
        $path = $this->normalizeStoragePath((string) $request->query('p', ''));

        // Block path traversal + absolute paths + windows-style drives
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || preg_match('#^[a-zA-Z]:#', $path)) {
            abort(403);
        }

        $base     = realpath(storage_path('app/public'));
        $fullPath = realpath($base . DIRECTORY_SEPARATOR . $path);

        // Reject if realpath resolved outside the public storage root
        if ($base === false || $fullPath === false || !str_starts_with($fullPath, $base)) {
            \Log::warning('serveFile: not found', [
                'requested' => $path,
                'base'      => $base,
                'fullPath'  => $fullPath,
            ]);
            abort(404);
        }

        if (!is_file($fullPath) || !is_readable($fullPath)) {
            \Log::warning('serveFile: unreadable', ['path' => $fullPath]);
            abort(404);
        }

        // Detect MIME from the file itself (hash-named files have no extension)
        $mime = function_exists('mime_content_type')
            ? (mime_content_type($fullPath) ?: 'application/octet-stream')
            : 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type'   => $mime,
            'Cache-Control'  => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function userData($user): array
    {
        return [
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'phone'     => $user->phone,
            'user_type' => $user->user_type,
            'avatar'    => $user->avatar,
            'school_id' => $user->school_id,
        ];
    }

    private function schoolData($school): array
    {
        return [
            'id'       => $school->id,
            'name'     => $school->name,
            'logo'     => $this->publicFileUrl($school->logo),
            'currency' => $school->currency ?? '₹',
            'features' => $school->features ?? [],
            'settings' => collect($school->settings ?? [])->only([
                'app_name', 'app_description', 'address_line1', 'address_line2',
                'zipcode', 'country', 'date_format', 'time_format',
            ])->toArray(),
        ];
    }

    private function stats($user, ?int $yearId, Request $request): array
    {
        $school = app('current_school');

        if ($user->isStudent() || $user->isParent()) {
            $studentId = $this->resolveStudentId($user, $request);
            if (!$studentId || !$yearId) return [];

            // Canonical formula — (present + late*0.5 + half_day*0.5) / working_days
            // working_days = total records minus 'holiday' rows (holidays aren't working days).
            $statusCount = Attendance::where('student_id', $studentId)->where('academic_year_id', $yearId)
                ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->toArray();
            $totalAtt    = array_sum($statusCount);
            $workingDays = max(0, $totalAtt - ($statusCount['holiday'] ?? 0));
            $weighted    = ($statusCount['present'] ?? 0)
                         + ($statusCount['late']     ?? 0) * 0.5
                         + ($statusCount['half_day'] ?? 0) * 0.5;
            $student     = Student::find($studentId);
            $feeSummary  = $student ? $this->feeService->getStudentFeeSummary($student, $yearId, $school->id) : [];

            return [
                'attendance_pct'      => $workingDays > 0 ? round($weighted / $workingDays * 100) : 0,
                'fee_balance'         => $feeSummary['balance']   ?? 0,
                'pending_assignments' => 0,
                'upcoming_exams'      => ExamSchedule::where('school_id', $school->id)
                    ->where('status', 'published')->where('academic_year_id', $yearId)->count(),
            ];
        }

        if ($user->isTeacher()) {
            $scope          = app(TeacherScopeService::class)->for($user);
            $studentCount   = Student::where('school_id', $school->id)
                ->enrolledInYear($yearId)
                ->when($scope->restricted && $scope->sectionIds->isNotEmpty(), function ($q) use ($scope) {
                    $q->whereHas('academicHistories', fn($ah) => $ah->whereIn('section_id', $scope->sectionIds));
                })
                ->count();
            return [
                'classes_today'  => 0,
                'total_students' => $studentCount,
                'pending_marks'  => 0,
                'leave_requests' => 0,
            ];
        }

        // Admin stats — rich KPI payload
        $totalStudents = Student::where('school_id', $school->id)->enrolledInYear($yearId)->count();
        $totalStaff    = \App\Models\Staff::where('school_id', $school->id)->count();

        // Daily / Monthly / Yearly collected — sum across all four fee streams
        // (tuition, transport, hostel, stationary) so the KPI matches Day Book.
        $sumPaymentsToday = function (string $modelClass) use ($school) {
            return (float) $modelClass::where('school_id', $school->id)
                ->whereDate('payment_date', today())
                ->where('status', '!=', 'cancelled')
                ->sum('amount_paid');
        };
        $sumPaymentsMonth = function (string $modelClass) use ($school) {
            return (float) $modelClass::where('school_id', $school->id)
                ->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->where('status', '!=', 'cancelled')
                ->sum('amount_paid');
        };
        $sumPaymentsYear = function (string $modelClass) use ($school, $yearId) {
            $q = $modelClass::where('school_id', $school->id)->where('status', '!=', 'cancelled');
            if ($yearId) $q->where('academic_year_id', $yearId);
            return (float) $q->sum('amount_paid');
        };

        $feeToday = $sumPaymentsToday(FeePayment::class)
            + $sumPaymentsToday(\App\Models\TransportFeePayment::class)
            + $sumPaymentsToday(\App\Models\HostelFeePayment::class)
            + $sumPaymentsToday(\App\Models\StationaryFeePayment::class);

        $feeMonth = $sumPaymentsMonth(FeePayment::class)
            + $sumPaymentsMonth(\App\Models\TransportFeePayment::class)
            + $sumPaymentsMonth(\App\Models\HostelFeePayment::class)
            + $sumPaymentsMonth(\App\Models\StationaryFeePayment::class);

        $feeYear = $sumPaymentsYear(FeePayment::class)
            + $sumPaymentsYear(\App\Models\TransportFeePayment::class)
            + $sumPaymentsYear(\App\Models\HostelFeePayment::class)
            + $sumPaymentsYear(\App\Models\StationaryFeePayment::class);

        $feePendingQ = FeePayment::where('school_id', $school->id)
            ->where('status', '!=', 'cancelled')
            ->where('balance', '>', 0);
        if ($yearId) $feePendingQ->where('academic_year_id', $yearId);
        $feePending = (float) $feePendingQ->sum('balance');

        // Today's attendance — across the whole school
        $attTodayQ = Attendance::where('school_id', $school->id)
            ->whereDate('date', today());
        if ($yearId) $attTodayQ->where('academic_year_id', $yearId);
        $attTodayRecords = (clone $attTodayQ)->get(['status']);
        $attTodayTotal   = $attTodayRecords->count();
        $attTodayPresent = $attTodayRecords->whereIn('status', ['present', 'late'])->count();
        $attTodayAbsent  = $attTodayRecords->where('status', 'absent')->count();
        $attTodayPct     = $attTodayTotal > 0 ? round($attTodayPresent / $attTodayTotal * 100) : 0;

        return [
            'total_students'       => $totalStudents,
            'total_staff'          => $totalStaff,
            'fee_collected_today'  => $feeToday,
            'fee_collected_month'  => $feeMonth,
            'fee_collected_year'   => $feeYear,
            'fee_pending_total'    => $feePending,
            'today_attendance_pct' => $attTodayPct,
            'today_present'        => $attTodayPresent,
            'today_absent'         => $attTodayAbsent,
            'today_marked'         => $attTodayTotal,
            'today_unmarked'       => max(0, $totalStudents - $attTodayTotal),
        ];
    }

    /**
     * Rich admin dashboard payload — gender ratio, 7-day attendance trend,
     * 6-month fee collection trend, class/section counts.
     */
    private function adminDashboardData(int $schoolId, ?int $yearId): array
    {
        // Gender breakdown
        $gender = Student::where('school_id', $schoolId)
            ->enrolledInYear($yearId)
            ->selectRaw('LOWER(COALESCE(gender, "")) as g, COUNT(*) as c')
            ->groupBy('g')
            ->pluck('c', 'g');
        $boys      = (int) ($gender['male']   ?? 0);
        $girls     = (int) ($gender['female'] ?? 0);
        $totalStud = (int) Student::where('school_id', $schoolId)->enrolledInYear($yearId)->count();
        $other     = max(0, $totalStud - $boys - $girls);

        // Class + section counts
        $totalClasses  = \App\Models\CourseClass::where('school_id', $schoolId)->count();
        $totalSections = \App\Models\Section::where('school_id', $schoolId)->forCurrentYear()->count();

        // 7-day attendance trend (today going back 6 days)
        $attTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day   = now()->subDays($i);
            $dayQ  = Attendance::where('school_id', $schoolId)->whereDate('date', $day);
            if ($yearId) $dayQ->where('academic_year_id', $yearId);
            $recs  = (clone $dayQ)->get(['status']);
            $tot   = $recs->count();
            $pres  = $recs->whereIn('status', ['present', 'late'])->count();
            $attTrend[] = [
                'label' => $day->format('D'),
                'date'  => $day->toDateString(),
                'pct'   => $tot > 0 ? round($pres / $tot * 100) : 0,
                'total' => $tot,
            ];
        }

        // 6-month fee collection trend (current month back 5)
        // Sums across all four fee streams (tuition, transport, hostel, stationary).
        $feeTrend = [];
        $feeStreamModels = [
            FeePayment::class,
            \App\Models\TransportFeePayment::class,
            \App\Models\HostelFeePayment::class,
            \App\Models\StationaryFeePayment::class,
        ];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end   = now()->subMonths($i)->endOfMonth();
            $sum   = 0.0;
            foreach ($feeStreamModels as $modelClass) {
                $sum += (float) $modelClass::where('school_id', $schoolId)
                    ->where('status', '!=', 'cancelled')
                    ->whereBetween('payment_date', [$start, $end])
                    ->sum('amount_paid');
            }
            $feeTrend[] = [
                'label'  => $start->format('M'),
                'month'  => $start->format('Y-m'),
                'amount' => $sum,
            ];
        }

        return [
            'gender' => [
                'boys'  => $boys,
                'girls' => $girls,
                'other' => max(0, $other),
            ],
            'total_classes'     => $totalClasses,
            'total_sections'    => $totalSections,
            'attendance_trend'  => $attTrend,
            'fee_trend'         => $feeTrend,
        ];
    }

    private function recentAnnouncements(int $schoolId, int $limit): array
    {
        return Announcement::where('school_id', $schoolId)->where('is_broadcasted', true)
            ->orderByDesc('created_at')->limit($limit)
            ->get(['id', 'title', 'delivery_method', 'created_at'])->toArray();
    }

    private function attendanceSummary($user, ?int $yearId, Request $request): array
    {
        $studentId = $this->resolveStudentId($user, $request);
        if (!$studentId || !$yearId) return [];

        // Today's attendance for dashboard display
        $todayRecord = Attendance::where('student_id', $studentId)
            ->where('academic_year_id', $yearId)
            ->whereDate('date', today())
            ->first();

        // Current month records for breakdown
        $records = Attendance::where('student_id', $studentId)
            ->where('academic_year_id', $yearId)
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
            ->get();

        $present  = $records->where('status', 'present')->count();
        $absent   = $records->where('status', 'absent')->count();
        $late     = $records->where('status', 'late')->count();
        $halfDay  = $records->where('status', 'half_day')->count();
        $leave    = $records->where('status', 'leave')->count();
        $holiday  = $records->where('status', 'holiday')->count();
        $total    = $records->count();
        $workingDays = max(0, $total - $holiday);

        return [
            'present'        => $present,
            'absent'         => $absent,
            'late'           => $late,
            'half_day'       => $halfDay,
            'leave'          => $leave,
            'holiday'        => $holiday,
            'total'          => $total,
            'working_days'   => $workingDays,
            // Canonical: (present + late*0.5 + half_day*0.5) / working_days (excludes holidays)
            'attendance_pct' => $workingDays > 0 ? round(($present + $late * 0.5 + $halfDay * 0.5) / $workingDays * 100) : 0,
            'today_status'   => $todayRecord?->status ?? 'not_marked',
            'month'          => now()->format('M Y'),
        ];
    }

    // ── Student Leave Management ────────────────────────────────────────────────

    public function leaveTypes(Request $request): JsonResponse
    {
        $school    = app('current_school');
        $studentId = $this->resolveStudentId($request->user(), $request);

        $types = LeaveType::where('school_id', $school->id)
            ->forStudents()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'color', 'days_allowed', 'requires_document', 'min_notice_days']);

        // Calculate used days per type for this student in the current academic year
        $balance = [];
        if ($studentId) {
            $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
            $usedQuery = StudentLeave::where('school_id', $school->id)
                ->where('student_id', $studentId)
                ->where('status', '!=', 'rejected');

            // Scope to current academic year if available
            if ($yearId) {
                $academicYear = AcademicYear::find($yearId);
                if ($academicYear) {
                    $usedQuery->where('start_date', '>=', $academicYear->start_date)
                              ->where('start_date', '<=', $academicYear->end_date);
                }
            }

            // PHP-side day counting — works on MySQL, SQLite, Postgres alike.
            // The previous julianday() approach was SQLite-only and silently
            // returned 0 on MySQL production deployments.
            $usedLeaves = (clone $usedQuery)
                ->get(['leave_type_id', 'start_date', 'end_date']);
            $usedByType = [];
            foreach ($usedLeaves as $leave) {
                if (!$leave->leave_type_id) continue;
                $days = Carbon::parse($leave->start_date)->diffInDays(Carbon::parse($leave->end_date)) + 1;
                $usedByType[$leave->leave_type_id] = ($usedByType[$leave->leave_type_id] ?? 0) + $days;
            }

            foreach ($types as $type) {
                $used = (int) ($usedByType[$type->id] ?? 0);
                $balance[$type->id] = [
                    'allowed'   => $type->days_allowed,
                    'used'      => $used,
                    'remaining' => max(0, $type->days_allowed - $used),
                ];
            }
        }

        return response()->json([
            'leave_types' => $types,
            'balance'     => $balance,
        ]);
    }

    public function leaves(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $studentId = $this->resolveStudentId($user, $request);

        $query = StudentLeave::where('school_id', $school->id)
            ->with(['leaveType:id,name,code,color', 'approver:id,name', 'appliedBy:id,name'])
            ->orderByDesc('created_at');

        // Scope based on role
        if ($user->isStudent()) {
            if (!$studentId) return response()->json(['leaves' => [], 'summary' => []]);
            $query->where('student_id', $studentId);
        } elseif ($user->isParent()) {
            $parent = $user->studentParent;
            if (!$parent) return response()->json(['leaves' => [], 'summary' => []]);
            $childIds = $parent->students()->pluck('id');
            if ($childIds->isEmpty()) return response()->json(['leaves' => [], 'summary' => []]);
            $query->whereIn('student_id', $childIds);
        } elseif ($user->isTeacher()) {
            // Teachers only see leaves for students in their assigned sections
            $scope = app(TeacherScopeService::class)->for($user);
            if ($scope->restricted) {
                if ($scope->sectionIds->isEmpty()) {
                    return response()->json(['leaves' => [], 'summary' => ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0], 'total' => 0, 'page' => 1]);
                }
                $studentIdsInScope = StudentAcademicHistory::where('school_id', $school->id)
                    ->whereIn('section_id', $scope->sectionIds)
                    ->pluck('student_id');
                $query->whereIn('student_id', $studentIdsInScope);
            }
        }
        // Admins see all leaves for the school

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        $leaves = $query->paginate(20);

        // Summary counts
        $summaryQuery = StudentLeave::where('school_id', $school->id);
        if ($user->isStudent() && $studentId) {
            $summaryQuery->where('student_id', $studentId);
        } elseif ($user->isParent()) {
            $parent = $user->studentParent;
            if ($parent) {
                $childIds = $parent->students()->pluck('id');
                $summaryQuery->whereIn('student_id', $childIds);
            }
        } elseif ($user->isTeacher()) {
            $scope = app(TeacherScopeService::class)->for($user);
            if ($scope->restricted && $scope->sectionIds->isNotEmpty()) {
                $studentIdsInScope = StudentAcademicHistory::where('school_id', $school->id)
                    ->whereIn('section_id', $scope->sectionIds)
                    ->pluck('student_id');
                $summaryQuery->whereIn('student_id', $studentIdsInScope);
            }
        }

        $statusCounts = (clone $summaryQuery)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $summary = [
            'total'    => $statusCounts->sum(),
            'pending'  => $statusCounts['pending']  ?? 0,
            'approved' => $statusCounts['approved'] ?? 0,
            'rejected' => $statusCounts['rejected'] ?? 0,
        ];

        // Add _display variants formatted per the school's System Config so
        // mobile screens can render dates without re-parsing locale-style.
        $dateFmt = $school->dateFmt();
        $leavesData = collect($leaves->items())->map(function ($l) use ($dateFmt) {
            $arr = $l->toArray();
            $arr['start_date_display'] = $l->start_date instanceof \Carbon\Carbon
                ? $l->start_date->format($dateFmt) : ($l->start_date ? \Carbon\Carbon::parse($l->start_date)->format($dateFmt) : null);
            $arr['end_date_display']   = $l->end_date instanceof \Carbon\Carbon
                ? $l->end_date->format($dateFmt) : ($l->end_date ? \Carbon\Carbon::parse($l->end_date)->format($dateFmt) : null);
            return $arr;
        });

        return response()->json([
            'leaves'  => $leavesData,
            'summary' => $summary,
            'total'   => $leaves->total(),
            'page'    => $leaves->currentPage(),
        ]);
    }

    public function applyLeave(Request $request): JsonResponse
    {
        $user    = $request->user();
        $school  = app('current_school');

        // Students can only apply from today onward; management can backdate
        $startDateRule = ($user->isStudent() || $user->isParent())
            ? 'required|date|after_or_equal:today'
            : 'required|date';

        $validated = $request->validate([
            'student_id'    => 'required|exists:students,id',
            'leave_type_id' => 'nullable|exists:leave_types,id',
            'start_date'    => $startDateRule,
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'required|string|max:1000',
            'document'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Verify student belongs to this school
        $student = Student::where('id', $validated['student_id'])
            ->where('school_id', $school->id)
            ->firstOrFail();

        // Students can only apply for themselves
        if ($user->isStudent()) {
            $ownStudent = Student::where('school_id', $school->id)
                ->where('user_id', $user->id)->firstOrFail();
            if ($ownStudent->id !== $student->id) {
                return response()->json(['message' => 'You can only apply for your own leave.'], 403);
            }
        }

        // Parents can only apply for their own children
        if ($user->isParent()) {
            $isChild = Student::where('school_id', $school->id)
                ->where('id', $student->id)
                ->whereHas('studentParent', fn($q) => $q->where('user_id', $user->id))
                ->exists();
            if (!$isChild) {
                return response()->json(['message' => 'You can only apply for your own child\'s leave.'], 403);
            }
        }

        // Validate leave type
        if (!empty($validated['leave_type_id'])) {
            $leaveType = LeaveType::where('id', $validated['leave_type_id'])
                ->where('school_id', $school->id)
                ->forStudents()
                ->where('is_active', true)
                ->firstOrFail();

            if ($leaveType->min_notice_days > 0) {
                $minDate = now()->addDays($leaveType->min_notice_days)->toDateString();
                if ($validated['start_date'] < $minDate) {
                    return response()->json([
                        'message' => "This leave type requires at least {$leaveType->min_notice_days} day(s) advance notice.",
                        'errors'  => ['start_date' => ["Minimum {$leaveType->min_notice_days} day(s) notice required."]],
                    ], 422);
                }
            }

            // Balance check — reject if this application would exceed days_allowed
            if ($leaveType->days_allowed > 0) {
                $requestedDays = Carbon::parse($validated['start_date'])
                    ->diffInDays(Carbon::parse($validated['end_date'])) + 1;

                $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
                $usedQuery = StudentLeave::where('school_id', $school->id)
                    ->where('student_id', $student->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('status', '!=', 'rejected');
                if ($yearId) {
                    $year = AcademicYear::find($yearId);
                    if ($year) {
                        $usedQuery->whereDate('start_date', '>=', $year->start_date)
                                  ->whereDate('start_date', '<=', $year->end_date);
                    }
                }
                $usedDays = 0;
                foreach ($usedQuery->get(['start_date', 'end_date']) as $l) {
                    $usedDays += Carbon::parse($l->start_date)->diffInDays(Carbon::parse($l->end_date)) + 1;
                }
                $remaining = max(0, $leaveType->days_allowed - $usedDays);

                if ($requestedDays > $remaining) {
                    return response()->json([
                        'message' => "Insufficient leave balance. {$remaining} day(s) of {$leaveType->name} remaining; this request needs {$requestedDays}.",
                        'errors'  => ['leave_type_id' => ["Only {$remaining} day(s) remaining."]],
                        'balance' => ['allowed' => $leaveType->days_allowed, 'used' => $usedDays, 'remaining' => $remaining, 'requested' => $requestedDays],
                    ], 422);
                }
            }
        }

        // Handle document upload
        $documentPath = $documentOriginalName = $documentMime = null;
        if ($request->hasFile('document') && $request->file('document')->isValid()) {
            $file     = $request->file('document');
            $safeName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $folder   = "student-leaves/{$school->id}/{$student->id}";
            $documentPath         = $file->storeAs($folder, $safeName, 'local');
            $documentOriginalName = $file->getClientOriginalName();
            $documentMime         = $file->getMimeType();
        }

        $leave = StudentLeave::create([
            'school_id'              => $school->id,
            'student_id'             => $student->id,
            'leave_type_id'          => $validated['leave_type_id'] ?? null,
            'start_date'             => $validated['start_date'],
            'end_date'               => $validated['end_date'],
            'reason'                 => $validated['reason'],
            'status'                 => 'pending',
            'applied_by'             => $user->id,
            'document_path'          => $documentPath,
            'document_original_name' => $documentOriginalName,
            'document_mime'          => $documentMime,
        ]);

        $leave->load(['leaveType:id,name,code,color', 'appliedBy:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Leave application submitted successfully.',
            'leave'   => $leave,
        ], 201);
    }

    public function cancelLeave(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $leave = StudentLeave::where('school_id', $school->id)
            ->where('id', $id)
            ->firstOrFail();

        // Only the applicant can cancel, and only if still pending
        if ($leave->applied_by !== $user->id) {
            return response()->json(['message' => 'You can only cancel leaves you applied for.'], 403);
        }
        if ($leave->status !== 'pending') {
            return response()->json(['message' => 'Only pending leaves can be cancelled.'], 422);
        }

        $leave->delete();

        return response()->json(['success' => true, 'message' => 'Leave application cancelled.']);
    }

    // ── Social Buzz / Posts ──────────────────────────────────────────────────────

    public function posts(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $query = Post::where('school_id', $school->id)
            ->where('is_approved', true)
            ->with([
                'user:id,name,avatar,user_type',
                'media',
                'comments' => fn($q) => $q->with('user:id,name,avatar')->latest()->limit(3),
            ])
            ->withCount(['likes', 'allComments as comments_count', 'bookmarks'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');

        // Visibility scoping
        if ($user->isStudent() || $user->isParent()) {
            $studentId = $this->resolveStudentId($user, $request);
            $history   = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
                ->where('academic_year_id', app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null)
                ->first() : null;

            $query->where(function ($q) use ($history) {
                $q->where('visibility', 'school');
                if ($history) {
                    $q->orWhere(function ($sub) use ($history) {
                        $sub->where('visibility', 'class')
                            ->where('class_id', $history->class_id);
                    });
                }
            });
        } elseif ($user->isTeacher()) {
            $query->whereIn('visibility', ['school', 'staff', 'class']);
        }
        // Admin sees everything

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $posts = $query->paginate(15);

        // Attach user-specific flags
        $items = collect($posts->items())->map(function ($post) use ($user) {
            $post->is_liked      = $post->isLikedBy($user);
            $post->is_bookmarked = $post->isBookmarkedBy($user);
            $post->is_own        = $post->user_id === $user->id;
            return $post;
        });

        return response()->json([
            'posts'        => $items,
            'total'        => $posts->total(),
            'page'         => $posts->currentPage(),
            'last_page'    => $posts->lastPage(),
        ]);
    }

    public function createPost(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $validated = $request->validate([
            'content'    => 'required|string|max:5000',
            'visibility' => 'sometimes|in:school,class,staff',
            'type'       => 'sometimes|in:post,poll,event,achievement',
            'class_id'   => 'nullable|exists:course_classes,id',
            'tags'       => 'nullable|array',
            'tags.*'     => 'string|max:50',
            'media'      => 'nullable|array|max:10',
            'media.*'    => 'file|mimes:jpg,jpeg,png,gif,mp4,mov,pdf|max:20480',
        ]);

        $post = Post::create([
            'school_id'   => $school->id,
            'user_id'     => $user->id,
            'content'     => $validated['content'],
            'visibility'  => $validated['visibility'] ?? 'school',
            'type'        => $validated['type'] ?? 'post',
            'class_id'    => $validated['class_id'] ?? null,
            'tags'        => $validated['tags'] ?? [],
            'is_approved' => true, // auto-approve for now; can add moderation later
        ]);

        // Handle media uploads
        if ($request->hasFile('media')) {
            $sortOrder = 0;
            foreach ($request->file('media') as $file) {
                if (!$file->isValid()) continue;
                $safeName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs("posts/{$school->id}/{$post->id}", $safeName, 'public');

                $post->media()->create([
                    'file_path'     => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getMimeType(),
                    'sort_order'    => $sortOrder++,
                ]);
            }
        }

        $post->load(['user:id,name,avatar,user_type', 'media']);
        $post->loadCount(['likes', 'allComments as comments_count']);

        return response()->json([
            'success' => true,
            'post'    => $post,
        ], 201);
    }

    public function toggleLike(Request $request, int $postId): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $post = Post::where('school_id', $school->id)->findOrFail($postId);

        $existing = PostLike::where('post_id', $post->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            PostLike::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'type'    => $request->input('type', 'like'),
            ]);
            $liked = true;
        }

        return response()->json([
            'liked'       => $liked,
            'likes_count' => $post->likes()->count(),
        ]);
    }

    public function toggleBookmark(Request $request, int $postId): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $post = Post::where('school_id', $school->id)->findOrFail($postId);

        $existing = PostBookmark::where('post_id', $post->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $bookmarked = false;
        } else {
            PostBookmark::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);
            $bookmarked = true;
        }

        return response()->json([
            'bookmarked'      => $bookmarked,
            'bookmarks_count' => $post->bookmarks()->count(),
        ]);
    }

    public function postComments(Request $request, int $postId): JsonResponse
    {
        $school = app('current_school');
        $post   = Post::where('school_id', $school->id)->findOrFail($postId);

        $comments = PostComment::where('post_id', $post->id)
            ->whereNull('parent_id')
            ->with([
                'user:id,name,avatar,user_type',
                'replies' => fn($q) => $q->with('user:id,name,avatar,user_type')->orderBy('created_at'),
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'comments' => $comments->items(),
            'total'    => $comments->total(),
            'page'     => $comments->currentPage(),
        ]);
    }

    public function addComment(Request $request, int $postId): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $post = Post::where('school_id', $school->id)->findOrFail($postId);

        $validated = $request->validate([
            'comment'   => 'required|string|max:2000',
            'parent_id' => 'nullable|exists:post_comments,id',
        ]);

        // If replying, verify parent comment belongs to same post
        if (!empty($validated['parent_id'])) {
            PostComment::where('id', $validated['parent_id'])
                ->where('post_id', $post->id)
                ->firstOrFail();
        }

        $comment = PostComment::create([
            'post_id'   => $post->id,
            'user_id'   => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'comment'   => $validated['comment'],
        ]);

        $comment->load('user:id,name,avatar,user_type');

        return response()->json([
            'success'        => true,
            'comment'        => $comment,
            'comments_count' => $post->allComments()->count(),
        ], 201);
    }

    public function deletePost(Request $request, int $postId): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $post = Post::where('school_id', $school->id)->findOrFail($postId);

        // Only the author or admin can delete
        if ($post->user_id !== $user->id && !in_array($user->user_type->value, ['admin', 'super_admin', 'school_admin'])) {
            return response()->json(['message' => 'You can only delete your own posts.'], 403);
        }

        $post->delete(); // soft delete

        return response()->json(['success' => true, 'message' => 'Post deleted.']);
    }

    // ── Profile Edit Requests ────────────────────────────────────────────────

    /**
     * Get student profile data for the edit-request form.
     * Returns current field values so the app can pre-fill the form.
     */
    public function editRequestForm(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['message' => 'No student found.'], 404);
        }

        $student = Student::where('id', $studentId)
            ->where('school_id', $school->id)
            ->with(['studentParent'])
            ->firstOrFail();

        return response()->json([
            'student' => [
                'id'            => $student->id,
                'first_name'    => $student->first_name,
                'last_name'     => $student->last_name,
                'dob'           => $student->dob instanceof \Carbon\Carbon ? $student->dob->toDateString() : ($student->dob ?? null),
                'birth_place'   => $student->birth_place,
                'mother_tongue' => $student->mother_tongue,
                'blood_group'   => $student->blood_group,
                'religion'      => $student->religion,
                'caste'         => $student->caste,
                'category'      => $student->category,
                'aadhaar_no'    => $student->aadhaar_no,
                'address'       => $student->address,
            ],
            'parent' => $student->studentParent ? [
                'primary_phone'     => $student->studentParent->primary_phone,
                'father_name'       => $student->studentParent->father_name,
                'mother_name'       => $student->studentParent->mother_name,
                'guardian_name'     => $student->studentParent->guardian_name,
                'father_phone'      => $student->studentParent->father_phone,
                'mother_phone'      => $student->studentParent->mother_phone,
                'father_occupation' => $student->studentParent->father_occupation,
                'mother_occupation' => $student->studentParent->mother_occupation,
                'parent_address'    => $student->studentParent->address,
            ] : null,
        ]);
    }

    /**
     * Submit a profile edit request (parent/student).
     */
    public function submitEditRequest(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['message' => 'No student found.'], 404);
        }

        $student = Student::where('id', $studentId)
            ->where('school_id', $school->id)
            ->with(['studentParent'])
            ->firstOrFail();

        // Verify ownership
        if ($user->isStudent()) {
            $own = Student::where('school_id', $school->id)->where('user_id', $user->id)->first();
            if (!$own || $own->id !== $student->id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        }
        if ($user->isParent()) {
            $isChild = Student::where('school_id', $school->id)
                ->where('id', $student->id)
                ->whereHas('studentParent', fn($q) => $q->where('user_id', $user->id))
                ->exists();
            if (!$isChild) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        }

        $validated = $request->validate([
            'first_name'        => 'nullable|string|max:255',
            'last_name'         => 'nullable|string|max:255',
            'dob'               => 'nullable|date',
            'birth_place'       => 'nullable|string|max:255',
            'mother_tongue'     => 'nullable|string|max:50',
            'blood_group'       => 'nullable|string|max:10',
            'religion'          => 'nullable|string|max:50',
            'caste'             => 'nullable|string|max:50',
            'category'          => 'nullable|string|max:50',
            'aadhaar_no'        => 'nullable|string|max:20',
            'address'           => 'nullable|string',
            'primary_phone'     => 'nullable|string|max:20',
            'father_name'       => 'nullable|string|max:255',
            'mother_name'       => 'nullable|string|max:255',
            'guardian_name'     => 'nullable|string|max:255',
            'father_phone'      => 'nullable|string|max:20',
            'mother_phone'      => 'nullable|string|max:20',
            'father_occupation' => 'nullable|string|max:255',
            'mother_occupation' => 'nullable|string|max:255',
            'parent_address'    => 'nullable|string',
            'reason'            => 'nullable|string|max:1000',
        ]);

        $reason = $validated['reason'] ?? null;
        unset($validated['reason']);

        $requestedChanges = [];
        $checkVal = function ($key, $oldVal, $newVal) use (&$requestedChanges) {
            if ($newVal === '') $newVal = null;
            if ($newVal !== null && (string) $oldVal !== (string) $newVal) {
                $requestedChanges[$key] = $newVal;
            }
        };

        // Student fields
        $checkVal('first_name',    $student->first_name,    $validated['first_name'] ?? null);
        $checkVal('last_name',     $student->last_name,     $validated['last_name'] ?? null);
        $checkVal('dob',           $student->dob?->toDateString(), $validated['dob'] ?? null);
        $checkVal('birth_place',   $student->birth_place,   $validated['birth_place'] ?? null);
        $checkVal('mother_tongue', $student->mother_tongue,  $validated['mother_tongue'] ?? null);
        $checkVal('blood_group',   $student->blood_group,   $validated['blood_group'] ?? null);
        $checkVal('religion',      $student->religion,       $validated['religion'] ?? null);
        $checkVal('caste',         $student->caste,          $validated['caste'] ?? null);
        $checkVal('category',      $student->category,       $validated['category'] ?? null);
        $checkVal('aadhaar_no',    $student->aadhaar_no,    $validated['aadhaar_no'] ?? null);
        $checkVal('address',       $student->address,        $validated['address'] ?? null);

        // Parent fields
        if ($student->studentParent) {
            $p = $student->studentParent;
            $checkVal('primary_phone',     $p->primary_phone,     $validated['primary_phone'] ?? null);
            $checkVal('father_name',       $p->father_name,       $validated['father_name'] ?? null);
            $checkVal('mother_name',       $p->mother_name,       $validated['mother_name'] ?? null);
            $checkVal('guardian_name',     $p->guardian_name,     $validated['guardian_name'] ?? null);
            $checkVal('father_phone',      $p->father_phone,      $validated['father_phone'] ?? null);
            $checkVal('mother_phone',      $p->mother_phone,      $validated['mother_phone'] ?? null);
            $checkVal('father_occupation', $p->father_occupation, $validated['father_occupation'] ?? null);
            $checkVal('mother_occupation', $p->mother_occupation, $validated['mother_occupation'] ?? null);
            $checkVal('parent_address',    $p->address,            $validated['parent_address'] ?? null);
        }

        if (empty($requestedChanges)) {
            return response()->json([
                'message' => 'No changes detected. Please modify at least one field.',
            ], 422);
        }

        $editRequest = EditRequest::create([
            'school_id'         => $school->id,
            'user_id'           => $user->id,
            'requestable_type'  => Student::class,
            'requestable_id'    => $student->id,
            'requested_changes' => $requestedChanges,
            'reason'            => $reason,
            'status'            => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile update request submitted. Pending admin approval.',
            'edit_request' => $editRequest,
        ], 201);
    }

    /**
     * List edit requests for the current student / parent's children.
     */
    public function editRequests(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $query = EditRequest::where('school_id', $school->id)
            ->where('requestable_type', Student::class)
            ->with(['reviewer:id,name'])
            ->orderByDesc('created_at');

        if ($user->isStudent()) {
            $studentId = $this->resolveStudentId($user, $request);
            if (!$studentId) return response()->json(['requests' => []]);
            $query->where('requestable_id', $studentId);
        } elseif ($user->isParent()) {
            $parent = $user->studentParent;
            if (!$parent) return response()->json(['requests' => []]);
            $childIds = $parent->students()->pluck('id');
            $query->whereIn('requestable_id', $childIds);
        } else {
            // Admin/teacher: filter by user who submitted
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->paginate(15);

        return response()->json([
            'requests' => $requests->items(),
            'total'    => $requests->total(),
            'page'     => $requests->currentPage(),
        ]);
    }

    // ── Results / Report Card ─────────────────────────────────────────────────

    public function results(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId || !$yearId) {
            return response()->json(['schedules' => []]);
        }

        $history = StudentAcademicHistory::where('student_id', $studentId)
            ->where('academic_year_id', $yearId)
            ->first();

        if (!$history) {
            return response()->json(['schedules' => []]);
        }

        // Published exam schedules for this student's class
        $schedules = \App\Models\ExamSchedule::where('school_id', $school->id)
            ->where('academic_year_id', $yearId)
            ->where('course_class_id', $history->class_id)
            ->where('status', 'published')
            ->with(['examType:id,name,code'])
            ->get();

        // For each schedule, attach the student's marks
        $result = $schedules->map(function ($schedule) use ($studentId) {
            $subjects = \App\Models\ExamScheduleSubject::where('exam_schedule_id', $schedule->id)
                ->where('is_enabled', true)
                ->with(['subject:id,name', 'examAssessment.items'])
                ->get();

            $subjectResults = $subjects->map(function ($ss) use ($studentId) {
                $marks = \App\Models\ExamMark::where('student_id', $studentId)
                    ->where('exam_schedule_subject_id', $ss->id)
                    ->get(['marks_obtained', 'is_absent', 'exam_assessment_item_id']);

                $totalObtained = $marks->where('is_absent', false)->sum('marks_obtained');
                $isAbsent      = $marks->every(fn($m) => $m->is_absent);
                $maxMarks      = $ss->examAssessment?->items->sum('max_marks') ?? $ss->max_marks ?? 0;

                return [
                    'subject_name'   => $ss->subject->name ?? 'Unknown',
                    'max_marks'      => $maxMarks,
                    'marks_obtained' => $isAbsent ? null : $totalObtained,
                    'is_absent'      => $isAbsent,
                    'percentage'     => ($maxMarks > 0 && !$isAbsent)
                        ? round($totalObtained / $maxMarks * 100, 1) : null,
                ];
            });

            $totalMax     = $subjectResults->whereNotNull('max_marks')->sum('max_marks');
            $totalObtained= $subjectResults->whereNotNull('marks_obtained')->sum('marks_obtained');

            return [
                'schedule_id'   => $schedule->id,
                'exam_name'     => $schedule->examType->name ?? 'Exam',
                'exam_code'     => $schedule->examType->code ?? '',
                'subjects'      => $subjectResults,
                'total_max'     => $totalMax,
                'total_obtained'=> $totalObtained,
                'percentage'    => $totalMax > 0 ? round($totalObtained / $totalMax * 100, 1) : null,
            ];
        });

        return response()->json(['schedules' => $result]);
    }

    // ── Homework / Assignments ─────────────────────────────────────────────────

    public function homework(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);

        if (!$yearId) return response()->json(['assignments' => []]);

        $history = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
            ->where('academic_year_id', $yearId)->first() : null;

        $query = \App\Models\Assignment::where('school_id', $school->id)
            ->where('academic_year_id', $yearId)
            ->with(['subject:id,name', 'teacher.user:id,name'])
            ->orderByDesc('due_date');

        // Scope to student's class/section
        if ($history) {
            $query->where('class_id', $history->class_id)
                  ->where('section_id', $history->section_id);
        }

        // Filter: upcoming / all
        if ($request->input('filter') === 'upcoming') {
            $query->where('due_date', '>=', now());
        }

        // Eager-load submissions for this student to avoid N+1
        if ($studentId) {
            $query->with(['submissions' => fn($q) => $q->where('student_id', $studentId)]);
        }

        $dateFmt = $school->dateFmt();
        $assignments = $query->get()->map(function ($a) use ($studentId, $dateFmt) {
            $submission = $studentId ? $a->submissions->first() : null;

            $daysLeft = $a->due_date ? (int) now()->diffInDays($a->due_date, false) : 0;

            return [
                'id'                => $a->id,
                'title'             => $a->title,
                'description'       => $a->description,
                'subject'           => $a->subject?->name ?? '',
                'teacher'           => $a->teacher?->user?->name ?? '',
                'due_date'          => $a->due_date?->toDateString(),
                'due_date_display'  => $a->due_date?->format($dateFmt),
                'max_marks'         => $a->max_marks,
                'days_left'         => $daysLeft,
                'is_overdue'        => $daysLeft < 0,
                'submitted'         => $submission !== null,
                'grade'             => $submission?->marks !== null ? (string) $submission->marks : null,
                'has_attachments'   => !empty($a->attachments),
            ];
        });

        return response()->json(['assignments' => $assignments]);
    }

    // ── Holidays ─────────────────────────────────────────────────────────────

    public function holidays(Request $request): JsonResponse
    {
        $school = app('current_school');

        $dateFmt  = $school->dateFmt();
        $holidays = Holiday::where('school_id', $school->id)
            ->orderBy('date')
            ->get()
            ->map(fn($h) => [
                'id'               => $h->id,
                'name'             => $h->title,
                'date'             => $h->date?->toDateString(),
                'date_display'     => $h->date?->format($dateFmt),
                'end_date'         => $h->end_date?->toDateString(),
                'end_date_display' => $h->end_date?->format($dateFmt),
                'type'             => $h->type ?? 'holiday',
                'description'      => $h->description,
            ]);

        return response()->json(['data' => $holidays]);
    }

    // ── Student Diary ────────────────────────────────────────────────────────

    public function diary(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);

        $history = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->where('status', 'current')
            ->latest()->first() : null;

        $date = $request->input('date', now()->toDateString());
        $page = $request->input('page', 1);

        $entries = StudentDiary::where('school_id', $school->id)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->when($history, fn($q) => $q->where('class_id', $history->class_id)->where('section_id', $history->section_id))
            ->when(! $history && $user->staff, function ($q) use ($user) {
                // Teacher calling this endpoint: apply incharge scope (replaces the
                // student history filter). Without this, the query returns ALL school diaries.
                app(\App\Services\TeacherScopeService::class)->applySubjectScope($q, app(\App\Services\TeacherScopeService::class)->for($user));
            })
            ->whereDate('date', $date)
            ->with(['subject:id,name', 'teacher.user:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(20, ['*'], 'page', $page);

        // Get completion data for current student
        $diaryIds    = collect($entries->items())->pluck('id');
        $completions = $studentId
            ? \App\Models\DiaryCompletion::whereIn('diary_id', $diaryIds)
                ->where('student_id', $studentId)->pluck('diary_id')->toArray()
            : [];
        $completionCounts = \App\Models\DiaryCompletion::whereIn('diary_id', $diaryIds)
            ->selectRaw('diary_id, count(*) as cnt')
            ->groupBy('diary_id')->pluck('cnt', 'diary_id')->toArray();

        $dateFmt = $school->dateFmt();
        $data = collect($entries->items())->map(fn($d) => [
            'id'               => $d->id,
            'subject'          => $d->subject?->name ?? 'General',
            'teacher'          => $d->teacher?->user?->name ?? '',
            'type'             => $d->subject_id ? 'classwork' : 'notice',
            'content'          => $d->content,
            'attachments'      => $this->absolutizeAttachments($d->attachments ?? []),
            'completed'        => in_array($d->id, $completions),
            'completion_count' => $completionCounts[$d->id] ?? 0,
            'date'             => $d->date?->toDateString(),
            'date_display'     => $d->date?->format($dateFmt),
            'created_at'       => $d->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data'         => $data,
            'current_page' => $entries->currentPage(),
            'last_page'    => $entries->lastPage(),
        ]);
    }

    public function toggleDiaryComplete(Request $request, $id): JsonResponse
    {
        $user      = $request->user();
        $studentId = $this->resolveStudentId($user, $request);
        if (!$studentId) return response()->json(['message' => 'Student not found'], 404);

        $diary = StudentDiary::findOrFail($id);

        // Check if already completed
        $existing = \App\Models\DiaryCompletion::where('diary_id', $id)
            ->where('student_id', $studentId)->first();

        if ($existing) {
            $existing->delete();
            $completed = false;
        } else {
            \App\Models\DiaryCompletion::create([
                'diary_id'   => $id,
                'student_id' => $studentId,
            ]);
            $completed = true;
        }

        $completionCount = \App\Models\DiaryCompletion::where('diary_id', $id)->count();

        return response()->json([
            'completed'        => $completed,
            'completion_count' => $completionCount,
        ]);
    }

    public function submitHomework(Request $request, $id): JsonResponse
    {
        $user      = $request->user();
        $studentId = $this->resolveStudentId($user, $request);
        if (!$studentId) return response()->json(['message' => 'Student not found'], 404);

        $assignment = \App\Models\Assignment::findOrFail($id);

        $request->validate([
            'content' => 'nullable|string|max:5000',
        ]);

        $submission = \App\Models\AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $id, 'student_id' => $studentId],
            [
                'content'      => $request->input('content', ''),
                'submitted_at' => now(),
                'is_late'      => $assignment->due_date && now()->gt($assignment->due_date),
            ]
        );

        return response()->json(['success' => true, 'submitted' => true]);
    }

    // ── Syllabus ─────────────────────────────────────────────────────────────

    public function assignments(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);
        $page      = $request->input('page', 1);

        $history = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->where('status', 'current')
            ->latest()->first() : null;

        $assignments = \App\Models\Assignment::where('school_id', $school->id)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->when($history, fn($q) => $q->where('class_id', $history->class_id)->where('section_id', $history->section_id))
            ->when(! $history && $user->staff, function ($q) use ($user) {
                // Teacher calling this endpoint: apply incharge scope.
                app(\App\Services\TeacherScopeService::class)->applySubjectScope($q, app(\App\Services\TeacherScopeService::class)->for($user));
            })
            ->where('status', 'published')
            ->with(['subject:id,name', 'teacher.user:id,name'])
            ->orderByDesc('due_date')
            ->paginate(20, ['*'], 'page', $page);

        $submissionsMap = $studentId
            ? \App\Models\AssignmentSubmission::whereIn('assignment_id', collect($assignments->items())->pluck('id'))
                ->where('student_id', $studentId)
                ->get()->keyBy('assignment_id')
            : collect();

        $today = now()->toDateString();
        $dateFmt = $school->dateFmt();

        $data = collect($assignments->items())->map(function ($a) use ($submissionsMap, $today, $dateFmt) {
            $sub = $submissionsMap->get($a->id);
            if ($sub) {
                $status = $sub->marks !== null ? 'graded' : ($sub->is_late ? 'late' : 'submitted');
            } elseif ($a->due_date && $a->due_date->toDateString() < $today) {
                $status = 'missed';
            } else {
                $status = 'pending';
            }
            return [
                'id'                => $a->id,
                'title'             => $a->title,
                'description'       => $a->description,
                'subject'           => $a->subject?->name ?? '',
                'teacher'           => $a->teacher?->user?->name ?? '',
                'due_date'          => $a->due_date?->toDateString(),
                'due_date_display'  => $a->due_date?->format($dateFmt),
                'max_marks'         => $a->max_marks,
                'status'            => $status,
                'marks'             => $sub?->marks,
                'attachments'       => $this->absolutizeAttachments($a->attachments ?? []),
            ];
        });

        return response()->json([
            'data'         => $data,
            'current_page' => $assignments->currentPage(),
            'last_page'    => $assignments->lastPage(),
        ]);
    }

    public function syllabus(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);

        $history = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->where('status', 'current')
            ->latest()->first() : null;

        // Get all syllabus topics — filtered by student's class, teacher's scope, or all for admin
        $topics = SyllabusTopic::where('school_id', $school->id)
            ->when($history, fn($q) => $q->where('class_id', $history->class_id))
            ->when(! $history && $user->staff, function ($q) use ($user) {
                // Teacher calling this endpoint: apply incharge scope (class + subject level).
                app(\App\Services\TeacherScopeService::class)->applyClassSubjectScope($q, app(\App\Services\TeacherScopeService::class)->for($user));
            })
            ->with(['subject:id,name'])
            ->orderBy('subject_id')
            ->orderBy('sort_order')
            ->get();

        // Get completion statuses
        $topicIds = $topics->pluck('id');
        $statuses = SyllabusStatus::whereIn('topic_id', $topicIds)
            ->when($history, fn($q) => $q->where('section_id', $history->section_id))
            ->get()
            ->keyBy('topic_id');

        // Get subject teachers
        $subjectTeachers = \App\Models\ClassSubject::where('school_id', $school->id)
            ->when($history, fn($q) => $q->where('course_class_id', $history->class_id)->where('section_id', $history->section_id))
            ->whereNotNull('incharge_staff_id')
            ->with('inchargeStaff.user:id,name')
            ->get()
            ->keyBy('subject_id');

        // Group by subject
        $subjects = $topics->groupBy('subject_id')->map(function ($subjectTopics) use ($statuses, $subjectTeachers) {
            $subject = $subjectTopics->first()?->subject;
            $topicList = $subjectTopics->map(function ($t) use ($statuses) {
                $status = $statuses->get($t->id);
                return [
                    'id'        => $t->id,
                    'name'      => $t->topic_name,
                    'chapter'   => $t->chapter_name,
                    'completed' => $status?->status === 'completed',
                ];
            });

            $total     = $topicList->count();
            $completed = $topicList->where('completed', true)->count();
            $teacher   = $subjectTeachers->get($subject?->id)?->inchargeStaff?->user?->name ?? '';

            return [
                'id'               => $subject?->id,
                'name'             => $subject?->name ?? 'Unknown',
                'teacher'          => $teacher,
                'topics'           => $topicList->values(),
                'total_topics'     => $total,
                'completed_topics' => $completed,
            ];
        })->values();

        return response()->json(['data' => ['subjects' => $subjects]]);
    }

    // ── Report Cards ─────────────────────────────────────────────────────────

    public function reportCards(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['data' => []]);
        }

        $history = StudentAcademicHistory::where('student_id', $studentId)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->latest()
            ->first();

        if (!$history) {
            return response()->json(['data' => []]);
        }

        // Get published exam schedules for this class
        $schedules = ExamSchedule::where('school_id', $school->id)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->where('course_class_id', $history->class_id)
            ->where('status', 'published')
            ->with(['examType:id,name,code', 'academicYear:id,name', 'scheduleSubjects.subject:id,name'])
            ->latest()
            ->get();

        $reportCards = $schedules->map(function ($schedule) use ($studentId) {
            $subjects = [];
            $totalObtained = 0;
            $totalMax = 0;

            foreach ($schedule->scheduleSubjects as $ss) {
                $marks = ExamMark::where('student_id', $studentId)
                    ->where('exam_schedule_subject_id', $ss->id)
                    ->get();

                $obtained = $marks->sum('marks_obtained');
                $maxMarks = $ss->max_marks ?? $marks->count() * 100;

                $totalObtained += $obtained;
                $totalMax += $maxMarks;

                $subjects[] = [
                    'name'           => $ss->subject?->name ?? 'Unknown',
                    'marks_obtained' => round($obtained, 1),
                    'max_marks'      => $maxMarks,
                    'grade'          => $this->calculateGrade($obtained, $maxMarks),
                    'is_absent'      => $marks->contains('is_absent', true),
                ];
            }

            $percentage = $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 1) : 0;

            return [
                'id'                  => $schedule->id,
                'term_name'           => $schedule->examType?->name ?? 'Exam',
                'academic_year'       => $schedule->academicYear?->name ?? '',
                'issued_date'         => $schedule->updated_at?->toDateString(),
                'issued_date_display' => $schedule->updated_at?->format(app('current_school')->dateFmt()),
                'overall_percentage'  => $percentage,
                'overall_grade'       => $this->calculateGrade($totalObtained, $totalMax),
                'rank'                => null,
                'status'              => 'Published',
                'subjects'            => $subjects,
                'total_obtained'      => round($totalObtained, 1),
                'total_max'           => $totalMax,
                'teacher_remarks'     => null,
                'principal_remarks'   => null,
            ];
        });

        return response()->json(['data' => $reportCards]);
    }

    private function calculateGrade(float $obtained, float $max): string
    {
        if ($max <= 0) return '-';
        $pct = ($obtained / $max) * 100;
        if ($pct >= 90) return 'A+';
        if ($pct >= 80) return 'A';
        if ($pct >= 70) return 'B+';
        if ($pct >= 60) return 'B';
        if ($pct >= 50) return 'C';
        if ($pct >= 40) return 'D';
        return 'F';
    }

    // ── Complaints ───────────────────────────────────────────────────────────

    public function complaints(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');
        $page   = $request->input('page', 1);
        $status = $request->input('status');

        $query = Complaint::where('school_id', $school->id)
            ->where('raised_by_id', $user->id)
            ->when($status, fn($q) => $q->where('status', $status))
            ->with(['assignedDepartment:id,name', 'assignedTo:id,name'])
            ->latest();

        $complaints = $query->paginate(20, ['*'], 'page', $page);

        $data = collect($complaints->items())->map(fn($c) => [
            'id'          => $c->id,
            'subject'     => $c->type . ' Complaint',
            'description' => $c->description,
            'category'    => $c->type,
            'status'      => $c->status,
            'priority'    => $c->priority,
            'response'    => $c->resolution_notes,
            'assigned_to' => $c->assignedTo?->name,
            'department'  => $c->assignedDepartment?->name,
            'created_at'  => $c->created_at?->toIso8601String(),
            'resolved_at' => $c->resolved_at?->toIso8601String(),
        ]);

        return response()->json([
            'data'         => $data,
            'current_page' => $complaints->currentPage(),
            'last_page'    => $complaints->lastPage(),
        ]);
    }

    public function submitComplaint(Request $request): JsonResponse
    {
        $request->validate([
            'description' => 'required|string|max:2000',
            'type'        => 'required|in:Academic,Transport,Hostel,Facility,Other',
            'priority'    => 'sometimes|in:Low,Medium,High,Critical',
        ]);

        $user   = $request->user();
        $school = app('current_school');

        $complaint = Complaint::create([
            'school_id'       => $school->id,
            'type'            => $request->input('type'),
            'raised_by_type'  => get_class($user),
            'raised_by_id'    => $user->id,
            'description'     => $request->input('description'),
            'priority'        => $request->input('priority', 'Medium'),
            'status'          => 'Open',
        ]);

        return response()->json([
            'data' => [
                'id'          => $complaint->id,
                'subject'     => $complaint->type . ' Complaint',
                'description' => $complaint->description,
                'category'    => $complaint->type,
                'status'      => $complaint->status,
                'created_at'  => $complaint->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    // ── Library / Book List ──────────────────────────────────────────────────

    public function bookList(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);
        $search    = $request->input('search');
        $page      = $request->input('page', 1);

        // Get student's class
        $history = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->latest()
            ->first() : null;

        $query = BookList::where('school_id', $school->id)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->when($history, fn($q) => $q->where('class_id', $history->class_id))
            ->when($search, fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('book_name', 'like', "%{$search}%")
                   ->orWhere('author', 'like', "%{$search}%");
            }))
            ->with(['subject:id,name', 'courseClass:id,name'])
            ->orderBy('subject_id')
            ->paginate(30, ['*'], 'page', $page);

        $data = collect($query->items())->map(fn($b) => [
            'id'          => $b->id,
            'name'        => $b->book_name,
            'author'      => $b->author,
            'publisher'   => $b->publisher,
            'isbn'        => $b->isbn,
            'subject'     => $b->subject?->name ?? '',
            'class_name'  => $b->courseClass?->name ?? '',
            'is_required' => true,
        ]);

        return response()->json([
            'data'         => $data,
            'current_page' => $query->currentPage(),
            'last_page'    => $query->lastPage(),
        ]);
    }

    public function resources(Request $request): JsonResponse
    {
        $school = app('current_school');

        // LearningMaterial may not exist in all setups — return empty gracefully
        if (!class_exists(\App\Models\LearningMaterial::class)) {
            return response()->json(['data' => []]);
        }

        $user      = $request->user();
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);
        $subjectId = $request->input('subject_id');
        $page      = $request->input('page', 1);

        $history = $studentId ? StudentAcademicHistory::where('student_id', $studentId)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->latest()
            ->first() : null;

        $query = \App\Models\LearningMaterial::where('school_id', $school->id)
            ->where('is_published', true)
            ->when($history, fn($q) => $q->where('class_id', $history->class_id))
            ->when($subjectId, fn($q) => $q->where('subject_id', $subjectId))
            ->with(['subject:id,name', 'teacher.user:id,name'])
            ->latest()
            ->paginate(20, ['*'], 'page', $page);

        $data = collect($query->items())->map(fn($r) => [
            'id'           => $r->id,
            'title'        => $r->title,
            'description'  => $r->description,
            'subject'      => $r->subject?->name ?? '',
            'teacher'      => $r->teacher?->user?->name ?? '',
            'type'         => strtolower($r->type ?? 'pdf'),
            'chapter_name' => $r->chapter_name,
            'file_url'     => $this->publicFileUrl($r->file_path),
            'external_url' => $r->external_url,
            'created_at'   => $r->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data'         => $data,
            'current_page' => $query->currentPage(),
            'last_page'    => $query->lastPage(),
        ]);
    }

    // ── Student ID Card ──────────────────────────────────────────────────────

    public function idCard(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        $student = Student::with([
            'currentAcademicHistory.courseClass',
            'currentAcademicHistory.section',
            'studentParent',
            'user:id,name,email,phone',
        ])->find($studentId);

        if (!$student) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        $history = $student->currentAcademicHistory;
        $parent  = $student->studentParent;

        return response()->json([
            'data' => [
                'student_id'    => $student->id,
                'name'          => $student->name,
                'photo_url'     => $student->photo_url,
                'admission_no'  => $student->admission_no,
                'roll_no'       => $student->roll_no,
                'dob'           => $student->dob,
                'gender'        => $student->gender,
                'blood_group'   => $student->blood_group,
                'class'         => $history?->courseClass?->name ?? '',
                'section'       => $history?->section?->name ?? '',
                'academic_year' => $history?->academicYear?->name ?? '',
                'parent_name'   => $parent?->father_name ?? $parent?->mother_name ?? $user->name,
                'contact'       => $user->phone ?? $parent?->phone ?? '',
                'email'         => $user->email ?? '',
                'address'       => $student->address ?? $parent?->address ?? '',
                'school' => [
                    'name'    => $school->name,
                    'logo'    => $this->publicFileUrl($school->logo),
                    'address' => $school->settings['address_line1'] ?? '',
                    'phone'   => $school->settings['phone'] ?? '',
                ],
            ],
        ]);
    }

    // ── Payment History ──────────────────────────────────────────────────────

    public function paymentHistory(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $studentId = $this->resolveStudentId($user, $request);
        $page      = $request->input('page', 1);

        if (!$studentId) {
            return response()->json(['data' => [], 'current_page' => 1, 'last_page' => 1]);
        }

        $payments = FeePayment::where('school_id', $school->id)
            ->where('student_id', $studentId)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->where('status', '!=', 'cancelled')
            ->with(['feeHead:id,name'])
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'page', $page);

        $dateFmt = $school->dateFmt();
        $data = collect($payments->items())->map(function ($p) use ($dateFmt) {
            $mode = $p->payment_mode instanceof \BackedEnum
                ? $p->payment_mode->value
                : $p->payment_mode;
            $status = $p->status instanceof \BackedEnum
                ? $p->status->value
                : $p->status;

            return [
                'id'                   => $p->id,
                'receipt_no'           => $p->receipt_no,
                'fee_head'             => $p->feeHead?->name ?? 'Other',
                'term'                 => $p->term,
                'amount'               => (float) $p->amount_paid,
                'amount_paid'          => (float) $p->amount_paid,
                'balance'              => (float) ($p->balance ?? 0),
                'payment_date'         => $p->payment_date?->toDateString(),
                'payment_date_display' => $p->payment_date?->format($dateFmt),
                'payment_mode'         => $mode,
                'status'               => strtolower((string) ($status ?? 'pending')),
                'transaction_id'       => $p->transaction_ref ?: $p->receipt_no,
                'transaction_ref'      => $p->transaction_ref,
                'created_at'           => $p->created_at?->toIso8601String(),
                'has_receipt'          => !empty($p->receipt_no),
            ];
        });

        return response()->json([
            'data'         => $data,
            'current_page' => $payments->currentPage(),
            'last_page'    => $payments->lastPage(),
        ]);
    }

    // ── Payments: Receipt (structured JSON for in-app viewer) ──────────────

    public function paymentReceipt(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $payment = FeePayment::where('school_id', $school->id)
            ->where('id', $id)
            ->with([
                'student:id,first_name,last_name,admission_no,erp_no',
                'feeHead.feeGroup:id,name',
                'collectedBy:id,name',
                'academicYear:id,name',
            ])
            ->firstOrFail();

        // Parents & students can only view their own / their child's receipts
        if ($user->isStudent() || $user->isParent()) {
            $studentId = $this->resolveStudentId($user, $request);
            if (!$studentId || $payment->student_id !== $studentId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $mode = $payment->payment_mode instanceof \App\Enums\PaymentMode
            ? $payment->payment_mode->value
            : $payment->payment_mode;

        return response()->json([
            'school' => [
                'name'     => $school->name,
                'address'  => $school->address ?? '',
                'city'     => $school->city ?? '',
                'state'    => $school->state ?? '',
                'zip_code' => $school->pincode ?? '',
                'phone'    => $school->phone ?? '',
                'email'    => $school->email ?? '',
                'currency' => $school->currency ?? '₹',
                'logo_url' => $this->publicFileUrl($school->logo),
            ],
            'payment' => [
                'id'                   => $payment->id,
                'receipt_no'           => $payment->receipt_no,
                'payment_date'         => $payment->payment_date?->toDateString(),
                'payment_date_display' => $payment->payment_date?->format($school->dateFmt()),
                'payment_mode'         => strtoupper($mode ?? ''),
                'transaction_ref' => $payment->transaction_ref,
                'fee_head'        => $payment->feeHead?->name ?? 'Other',
                'fee_group'       => $payment->feeHead?->feeGroup?->name ?? '',
                'term'            => str_replace('_', ' ', ucfirst((string) $payment->term)),
                'amount_due'      => (float) ($payment->amount_due ?? 0),
                'fine'            => (float) ($payment->fine ?? 0),
                'discount'        => (float) ($payment->discount ?? 0),
                'amount_paid'     => (float) ($payment->amount_paid ?? 0),
                'balance'         => (float) ($payment->balance ?? 0),
                'concession_note' => $payment->concession_note,
                'remarks'         => $payment->remarks,
                'collected_by'    => $payment->collectedBy?->name ?? 'Administrator',
                'academic_year'   => $payment->academicYear?->name,
            ],
            'student' => [
                'id'            => $payment->student?->id,
                'name'          => trim(($payment->student?->first_name ?? '') . ' ' . ($payment->student?->last_name ?? '')),
                'admission_no'  => $payment->student?->admission_no,
                'erp_no'        => $payment->student?->erp_no,
            ],
            'verification_url' => url("/verify-receipt/{$payment->receipt_no}"),
        ]);
    }

    // ── Payments: Create Order & Verify ────────────────────────────────────

    public function createPaymentOrder(Request $request): JsonResponse
    {
        $request->validate([
            'fee_ids' => 'required|array|min:1',
            'fee_ids.*' => 'integer|exists:fee_payments,id',
        ]);

        $user      = $request->user();
        $school    = app('current_school');
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['message' => 'No student linked to this account.'], 422);
        }

        $fees = FeePayment::where('school_id', $school->id)
            ->where('student_id', $studentId)
            ->whereIn('id', $request->input('fee_ids'))
            ->whereIn('status', ['due', 'partial'])
            ->get();

        if ($fees->isEmpty()) {
            return response()->json(['message' => 'No payable fees found for given IDs.'], 422);
        }

        $totalPaise = (int) round($fees->sum('balance') * 100);

        if ($totalPaise <= 0) {
            return response()->json(['message' => 'Total payable amount must be greater than zero.'], 422);
        }

        $receipt = 'MOBL-' . $school->id . '-' . $studentId . '-' . now()->timestamp;

        $razorpay = app(RazorpayService::class);
        $order = $razorpay->createOrder($totalPaise, 'INR', $receipt, [
            'school_id'  => (string) $school->id,
            'student_id' => (string) $studentId,
            'fee_ids'    => implode(',', $fees->pluck('id')->toArray()),
        ]);

        if (!$order) {
            return response()->json(['message' => 'Failed to create payment order. Please try again.'], 502);
        }

        return response()->json([
            'order_id'   => $order['id'],
            'amount'     => $totalPaise,
            'currency'   => $order['currency'] ?? 'INR',
            'key_id'     => config('payment.razorpay.key_id'),
            'receipt'    => $receipt,
            'fee_ids'    => $fees->pluck('id'),
            'student_id' => $studentId,
        ]);
    }

    public function verifyPayment(Request $request): JsonResponse
    {
        $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
            'fee_ids'             => 'required|array|min:1',
            'fee_ids.*'           => 'integer',
        ]);

        $razorpay = app(RazorpayService::class);
        $valid = $razorpay->verifySignature(
            $request->input('razorpay_order_id'),
            $request->input('razorpay_payment_id'),
            $request->input('razorpay_signature'),
        );

        if (!$valid) {
            return response()->json(['message' => 'Payment verification failed. Signature mismatch.'], 422);
        }

        $user      = $request->user();
        $school    = app('current_school');
        $studentId = $this->resolveStudentId($user, $request);

        $fees = FeePayment::where('school_id', $school->id)
            ->where('student_id', $studentId)
            ->whereIn('id', $request->input('fee_ids'))
            ->whereIn('status', ['due', 'partial'])
            ->get();

        $now = now();
        foreach ($fees as $fee) {
            $fee->update([
                'amount_paid'     => $fee->amount_due - $fee->discount + $fee->fine,
                'balance'         => 0,
                'status'          => 'paid',
                'payment_mode'    => 'Online',
                'payment_date'    => $now->toDateString(),
                'transaction_ref' => $request->input('razorpay_payment_id'),
                'collected_by'    => $user->id,
                'remarks'         => 'Paid via Razorpay Mobile App. Order: ' . $request->input('razorpay_order_id'),
            ]);
        }

        return response()->json([
            'message'    => 'Payment verified and recorded successfully.',
            'payment_id' => $request->input('razorpay_payment_id'),
            'fees_paid'  => $fees->pluck('id'),
        ]);
    }

    // ── Report Card Download ─────────────────────────────────────────────────

    public function downloadReportCard(Request $request, int $scheduleId): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $studentId = $this->resolveStudentId($user, $request);

        if (!$studentId) {
            return response()->json(['message' => 'No student linked.'], 422);
        }

        $schedule = ExamSchedule::where('school_id', $school->id)
            ->where('id', $scheduleId)
            ->where('status', 'published')
            ->with(['examType:id,name,code', 'academicYear:id,name', 'scheduleSubjects.subject:id,name'])
            ->firstOrFail();

        $student = Student::with('user:id,name')->find($studentId);

        $history = StudentAcademicHistory::where('student_id', $studentId)
            ->where('academic_year_id', $schedule->academic_year_id)
            ->first();

        $subjects = [];
        $totalObtained = 0;
        $totalMax = 0;

        foreach ($schedule->scheduleSubjects as $ss) {
            $marks = ExamMark::where('student_id', $studentId)
                ->where('exam_schedule_subject_id', $ss->id)
                ->get();

            $obtained = $marks->sum('marks_obtained');
            $maxMarks = $ss->max_marks ?? $marks->count() * 100;
            $totalObtained += $obtained;
            $totalMax += $maxMarks;

            $subjects[] = [
                'name'           => $ss->subject?->name ?? 'Unknown',
                'marks_obtained' => round($obtained, 1),
                'max_marks'      => $maxMarks,
                'grade'          => $this->calculateGrade($obtained, $maxMarks),
                'percentage'     => $maxMarks > 0 ? round(($obtained / $maxMarks) * 100, 1) : 0,
                'is_absent'      => $marks->contains('is_absent', true),
            ];
        }

        $percentage = $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 1) : 0;

        return response()->json([
            'student'      => [
                'name'        => $student?->user?->name ?? 'Student',
                'admission_no' => $student?->admission_no,
                'class'       => $history?->class_name ?? '',
                'section'     => $history?->section_name ?? '',
                'roll_no'     => $history?->roll_number,
            ],
            'exam'         => [
                'name'          => $schedule->examType?->name ?? 'Exam',
                'academic_year' => $schedule->academicYear?->name ?? '',
                'date'          => $schedule->updated_at?->toDateString(),
            ],
            'school'       => [
                'name'    => $school->name,
                'address' => $school->address ?? '',
                'logo'    => $school->logo_url ?? null,
            ],
            'subjects'            => $subjects,
            'total_obtained'      => round($totalObtained, 1),
            'total_max'           => $totalMax,
            'overall_percentage'  => $percentage,
            'overall_grade'       => $this->calculateGrade($totalObtained, $totalMax),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Haversine distance between two GPS coordinates in km.
     */
    private function transportHaversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // ── Student Attendance (Mark + Report) ───────────────────────────────────

    /**
     * GET /mobile/attendance/students?class_id=X&section_id=Y&date=YYYY-MM-DD
     * Returns student list with their existing attendance status for the given date.
     * roll_no lives on StudentAcademicHistory, not on Student.
     */
    public function attendanceStudents(Request $request): JsonResponse
    {
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $classId   = $request->input('class_id');
        $sectionId = $request->input('section_id');
        $date      = \Carbon\Carbon::parse($request->input('date', now()->toDateString()))->toDateString();

        if (!$classId) {
            return response()->json(['message' => 'class_id is required.'], 422);
        }

        // Match web: status='current' + order by roll_no in DB
        $histories = \App\Models\StudentAcademicHistory::where('school_id', $school->id)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->where('class_id', $classId)
            ->where('status', 'current')
            ->when($sectionId, fn($q) => $q->where('section_id', $sectionId))
            ->with('student:id,first_name,last_name,photo')
            ->orderBy('roll_no')
            ->get();

        // Existing attendance for this date
        $existing = \App\Models\Attendance::where('school_id', $school->id)
            ->where('date', $date)
            ->where('class_id', $classId)
            ->when($sectionId, fn($q) => $q->where('section_id', $sectionId))
            ->pluck('status', 'student_id');

        $students = $histories
            ->filter(fn($h) => $h->student !== null)
            ->map(fn($h) => [
                'id'      => $h->student->id,
                'name'    => trim(($h->student->first_name ?? '') . ' ' . ($h->student->last_name ?? '')),
                'roll_no' => $h->roll_no,   // roll_no is on academic_history, not student
                'status'  => $existing[$h->student_id] ?? null,
            ])
            ->values();

        return response()->json([
            'students'      => $students,
            'date'          => $date,
            'already_marked'=> $existing->isNotEmpty(),
        ]);
    }

    /**
     * POST /mobile/attendance/mark
     * Body: { class_id, section_id?, date, attendance: [{student_id, status, remarks?}], send_notifications? }
     * Matches web AttendanceController::store() exactly.
     */
    public function markAttendance(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');
        $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        // Robust user-type check (handles backed enum and plain string)
        $userType = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        if (!in_array($userType, ['admin', 'school_admin', 'principal', 'super_admin', 'teacher', 'staff'])) {
            return response()->json(['message' => 'Unauthorized. Only teachers and school management can mark attendance.'], 403);
        }

        if (!$yearId) {
            return response()->json(['message' => 'No active academic year found.'], 422);
        }

        $request->validate([
            'class_id'                => ['required', \Illuminate\Validation\Rule::exists('course_classes', 'id')->where('school_id', $school->id)],
            'section_id'              => ['nullable', \Illuminate\Validation\Rule::exists('sections', 'id')->where('school_id', $school->id)],
            'date'                    => 'required|date',
            'attendance'              => 'required|array|min:1',
            'attendance.*.student_id' => ['required', \Illuminate\Validation\Rule::exists('students', 'id')->where('school_id', $school->id)],
            'attendance.*.status'     => 'required|in:present,absent,late,half_day,leave,holiday',
            'attendance.*.remarks'    => 'nullable|string|max:255',
            'send_notifications'      => 'boolean',
        ]);

        // Scope guard: restrict teachers to sections/classes they are incharge of
        $scope = app(\App\Services\TeacherScopeService::class)->for($user);
        if ($scope->restricted) {
            $sectionId = $request->section_id ? (int) $request->section_id : null;
            $classId   = (int) $request->class_id;
            $allowed   = $sectionId
                ? $scope->sectionIds->contains($sectionId)
                : $scope->classIds->contains($classId);
            if (! $allowed) {
                return response()->json(['message' => 'Not assigned to this class or section.'], 403);
            }
        }

        $date      = \Carbon\Carbon::parse($request->date)->toDateString();
        $classId   = $request->class_id;
        $sectionId = $request->section_id;
        $saved     = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $school, $yearId, $classId, $sectionId, $date, $user, &$saved) {
            foreach ($request->attendance as $rec) {
                \App\Models\Attendance::updateOrCreate(
                    [
                        'school_id'  => $school->id,
                        'student_id' => $rec['student_id'],
                        'date'       => $date,
                    ],
                    [
                        'academic_year_id' => $yearId,
                        'class_id'         => $classId,
                        'section_id'       => $sectionId,
                        'status'           => $rec['status'],
                        'remarks'          => $rec['remarks'] ?? null,
                        'marked_by'        => $user->id,
                    ]
                );
                $saved++;
            }
        });

        // Optional notifications (matches web "Save & Send Notification" button)
        $notificationsSent = 0;
        if ($request->boolean('send_notifications')) {
            try {
                $notificationService = new \App\Services\NotificationService($school);
                $notifyAll   = $school->settings['notifications_v2']['attendance_notify_all'] ?? false;
                $studentIds  = collect($request->attendance)->pluck('student_id');
                $students    = \App\Models\Student::whereIn('id', $studentIds)
                    ->with(['studentParent', 'currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
                    ->get()->keyBy('id');

                foreach ($request->attendance as $rec) {
                    if ($rec['status'] === 'present' && !$notifyAll) continue;
                    $student = $students->get($rec['student_id']);
                    if ($student) {
                        try {
                            $notificationService->notifyAttendance($student, $rec['status']);
                            $notificationsSent++;
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning("Attendance notification failed for student {$student->id}: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Attendance notification batch failed: ' . $e->getMessage());
            }
        }

        $dateFormatted = \Carbon\Carbon::parse($date)->format('d M Y');
        $message = "Attendance saved for {$dateFormatted}. {$saved} students marked.";
        if ($request->boolean('send_notifications')) {
            $message .= " Notifications sent to {$notificationsSent} parents.";
        }

        return response()->json(['message' => $message, 'saved' => $saved, 'notifications_sent' => $notificationsSent]);
    }

    /**
     * POST /mobile/attendance/rapid-scan
     * Mark a single student "present" from a scanned QR code.
     *
     * Payload: { "uuid": "<raw-qr-data>" }
     *
     * The QR code can be either the raw student UUID or a URL of the form
     * "…/q/<uuid>" (which is what the web QRScanner emits). We strip the
     * URL wrapper with the same regex the Vue side uses.
     *
     * Mirrors AttendanceController::rapidScan() but with strict
     * school-id tenant scoping so a teacher can't mark attendance on a
     * student from a different school by guessing their UUID.
     */
    public function rapidScanAttendance(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');
        $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        // Robust user-type check — same gate as markAttendance
        $userType = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        if (!in_array($userType, ['admin', 'school_admin', 'principal', 'super_admin', 'teacher', 'staff'])) {
            return response()->json(['error' => 'Unauthorized. Only teachers and school management can mark attendance.'], 403);
        }

        if (!$yearId) {
            return response()->json(['error' => 'No active academic year found.'], 422);
        }

        $request->validate(['uuid' => 'required|string|max:512']);

        // Accept either the raw UUID or a "/q/<uuid>" URL (same shape the web
        // QRScanner.vue emits). Strip everything outside the segment.
        $raw = trim($request->uuid);
        if (preg_match('~/q/([^/?#]+)~', $raw, $m)) {
            $uuid = $m[1];
        } else {
            $uuid = $raw;
        }

        $student = \App\Models\Student::with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->where('school_id', $school->id)   // tenant-scope (fixes web IDOR)
            ->where('uuid', $uuid)
            ->first();

        if (!$student) {
            return response()->json(['error' => 'Student not found or invalid QR.'], 404);
        }

        $history = $student->currentAcademicHistory;
        if (!$history) {
            return response()->json(['error' => "{$student->name} has no active academic record."], 400);
        }

        // Teachers: verify the student is in one of their assigned sections
        if ($user->isTeacher()) {
            $scope = app(TeacherScopeService::class)->for($user);
            if ($scope->restricted && ! $scope->sectionIds->contains($history->section_id)) {
                return response()->json(['error' => 'You are not authorized to mark attendance for this student.'], 403);
            }
        }

        \App\Models\Attendance::updateOrCreate(
            [
                'school_id'  => $school->id,
                'student_id' => $student->id,
                'date'       => now()->toDateString(),
            ],
            [
                'academic_year_id' => $yearId,
                'class_id'         => $history->class_id,
                'section_id'       => $history->section_id,
                'status'           => 'present',
                'marked_by'        => $user->id,
            ]
        );

        return response()->json([
            'success'   => true,
            'message'   => 'Attendance marked Present.',
            'student'   => [
                'id'           => $student->id,
                'name'         => $student->name,
                'admission_no' => $student->admission_no,
                'class_name'   => $history->courseClass->name ?? 'N/A',
                'section_name' => $history->section->name ?? '',
                'photo_url'    => $this->publicFileUrl($student->photo),
            ],
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * GET /mobile/staff-qr/me
     * Returns the current staff member's QR code as a base64 PNG data URI,
     * so the mobile app can render <Image source={{ uri: data_uri }} />
     * without needing a QR-generation library on the client.
     *
     * The QR encodes the same /q/staff/<employee_id> URL the bulk PDF/Excel
     * exports use — so a phone-shown QR is interchangeable with a printed
     * badge at the scanner end.
     */
    public function staffQrSelf(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $staff = \App\Models\Staff::with('designation:id,name', 'department:id,name')
            ->where('user_id', $user->id)
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->first();

        if (!$staff) {
            return response()->json(['message' => 'No active staff record found.'], 404);
        }
        if (!$staff->employee_id) {
            return response()->json(['message' => 'Your record has no Employee ID — ask admin.'], 422);
        }

        $url    = url('/q/staff/' . $staff->employee_id);
        $qr     = new \Endroid\QrCode\QrCode($url);
        $qr->setSize(360);
        $qr->setMargin(8);
        $writer = new \Endroid\QrCode\Writer\PngWriter();
        $png    = $writer->write($qr)->getString();

        return response()->json([
            'data_uri'    => 'data:image/png;base64,' . base64_encode($png),
            'employee_id' => $staff->employee_id,
            'name'        => $user->name,
            'designation' => $staff->designation?->name,
            'department'  => $staff->department?->name,
            'school_name' => $school->name,
            'scan_url'    => $url,
        ]);
    }

    /**
     * POST /mobile/staff-attendance/rapid-scan
     * Mirrors the student rapid-scan flow for staff. Admin / principal /
     * school_admin scans a staff QR (which encodes the employee_id) and the
     * row is marked Present for today via updateOrCreate.
     *
     * QR payload formats accepted:
     *   - bare "EMP123"
     *   - "staff:EMP123"
     *   - "/staff/EMP123" or any URL containing /staff/<id>
     */
    public function rapidScanStaffAttendance(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        // Only management can mark staff attendance — same gate as the web
        // staff-attendance routes.
        $userType = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        if (!in_array($userType, ['admin', 'school_admin', 'principal', 'super_admin'])) {
            return response()->json(['error' => 'Only school management can mark staff attendance.'], 403);
        }

        $request->validate(['code' => 'required|string|max:512']);
        $raw = trim($request->code);

        // Pull the employee_id segment out of common payloads
        if (preg_match('~/staff/([^/?#]+)~', $raw, $m)) {
            $code = $m[1];
        } elseif (preg_match('~^staff:(.+)$~', $raw, $m)) {
            $code = $m[1];
        } else {
            $code = $raw;
        }

        $staff = \App\Models\Staff::with(['user:id,name', 'designation:id,name'])
            ->where('school_id', $school->id)
            ->where('employee_id', $code)
            ->where('status', 'active')
            ->first();

        if (!$staff) {
            return response()->json(['error' => 'Staff not found or inactive.'], 404);
        }

        // Determine late vs present using the school's late_threshold setting
        $lateThreshold = $school->settings['late_threshold'] ?? '09:30';
        $now           = now()->format('H:i:s');
        $status        = Carbon::parse($now)->gt(Carbon::parse($lateThreshold)) ? 'late' : 'present';

        StaffAttendance::updateOrCreate(
            [
                'school_id' => $school->id,
                'staff_id'  => $staff->id,
                'date'      => now()->toDateString(),
            ],
            [
                'status'    => $status,
                'check_in'  => $now,
                'marked_by' => $user->id,
            ]
        );

        return response()->json([
            'success'   => true,
            'message'   => $status === 'late' ? 'Marked Late.' : 'Marked Present.',
            'staff'     => [
                'id'          => $staff->id,
                'employee_id' => $staff->employee_id,
                'name'        => $staff->user?->name ?? 'Unknown',
                'designation' => $staff->designation?->name ?? '',
                'photo_url'   => $this->publicFileUrl($staff->photo ?? null),
            ],
            'status'    => $status,
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * POST /mobile/students/lookup-by-uuid
     * Resolves a scanned student QR (raw uuid or "/q/<uuid>" url) to the
     * minimum info the app needs to navigate into StudentDetailScreen.
     *
     * Tenant-scoped to current school. Used by the mobile QR profile scanner
     * — distinct from rapidScanAttendance, which marks attendance as a
     * side-effect. This one is a pure lookup, no writes.
     */
    public function lookupStudentByUuid(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        // Only school staff (admin / teachers) can use the directory lookup
        $userType = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        if (!in_array($userType, ['admin', 'school_admin', 'principal', 'super_admin', 'teacher', 'staff'])) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $request->validate(['uuid' => 'required|string|max:512']);

        $raw = trim($request->uuid);
        if (preg_match('~/q/([^/?#]+)~', $raw, $m)) {
            $uuid = $m[1];
        } else {
            $uuid = $raw;
        }

        $student = \App\Models\Student::with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->where('school_id', $school->id)
            ->where('uuid', $uuid)
            ->first();

        if (!$student) {
            return response()->json(['error' => 'Student not found or invalid QR.'], 404);
        }

        $history = $student->currentAcademicHistory;

        return response()->json([
            'success' => true,
            'student' => [
                'id'           => $student->id,
                'name'         => $student->name,
                'admission_no' => $student->admission_no,
                'class_name'   => $history->courseClass->name ?? null,
                'section_name' => $history->section->name ?? null,
                'photo_url'    => $this->publicFileUrl($student->photo),
            ],
        ]);
    }

    // ── Fee Collection (Admin) ────────────────────────────────────────────────
    // GET  /mobile/fees/collect/{studentId}  → returns student summary + fee heads list
    // POST /mobile/fees/collect              → records a fee payment (mirrors web collectStore)

    /**
     * Guard: only staff roles can collect fees.
     */
    private function assertCanCollectFees(Request $request): void
    {
        $user     = $request->user();
        $userType = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        if (!in_array($userType, ['admin', 'school_admin', 'principal', 'super_admin', 'staff', 'accountant'])) {
            abort(response()->json(['error' => 'Unauthorized.'], 403));
        }
    }

    /**
     * GET /mobile/fees/collect/{studentId}
     * Returns student + fee summary (total_due / paid / balance / head-wise breakdown)
     * so the mobile collection form can show what's pending.
     */
    public function feeCollectShow(Request $request, int $studentId): JsonResponse
    {
        $this->assertCanCollectFees($request);

        $school = app('current_school');
        $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        if (!$yearId) {
            return response()->json(['error' => 'No active academic year.'], 422);
        }

        $student = Student::with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->where('school_id', $school->id)
            ->where('id', $studentId)
            ->first();

        if (!$student) {
            return response()->json(['error' => 'Student not found.'], 404);
        }

        $summary = $this->feeService->getStudentFeeSummary($student, $yearId, $school->id);

        // Only show heads that still have an outstanding balance (status != paid)
        // FeeService statuses: 'paid' | 'partial' | 'unpaid'
        $collectable = collect($summary['fee_heads'] ?? [])
            ->filter(fn($h) => (float) ($h['balance'] ?? 0) > 0)
            ->values()
            ->all();

        $history = $student->currentAcademicHistory;

        return response()->json([
            'student' => [
                'id'           => $student->id,
                'name'         => $student->name,
                'admission_no' => $student->admission_no,
                'class_name'   => $history->courseClass->name ?? null,
                'section_name' => $history->section->name ?? null,
                'photo_url'    => $this->publicFileUrl($student->photo),
            ],
            'summary' => [
                'total_due' => (float) ($summary['total_due'] ?? 0),
                'paid'      => (float) ($summary['paid']      ?? 0),
                'discount'  => (float) ($summary['discount']  ?? 0),
                'balance'   => (float) ($summary['balance']   ?? 0),
            ],
            'fee_heads'     => $collectable,
            'all_fee_heads' => $summary['fee_heads'] ?? [],
            'payment_modes' => \App\Models\PaymentMethod::where('school_id', $student->school_id)
                ->where('is_active', true)
                ->orderBy('sort_order')->orderBy('label')
                ->pluck('code')
                ->all(),
        ]);
    }

    /**
     * POST /mobile/fees/collect
     * Records a fee payment — mirrors web FeeController::collectStore() validation
     * and balance/status computation.
     */
    public function feeCollectStore(Request $request): JsonResponse
    {
        $this->assertCanCollectFees($request);

        $school = app('current_school');
        $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        if (!$yearId) {
            return response()->json(['error' => 'No active academic year.'], 422);
        }

        $validated = $request->validate([
            'student_id'      => [
                'required',
                \Illuminate\Validation\Rule::exists('students', 'id')->where('school_id', $school->id),
            ],
            'fee_head_id'     => [
                'required',
                \Illuminate\Validation\Rule::exists('fee_heads', 'id')->where('school_id', $school->id),
            ],
            'term'            => 'required|string|max:50',
            'amount_due'      => 'required|numeric|min:0',
            'amount_paid'     => 'required|numeric|min:0',
            'discount'        => 'nullable|numeric|min:0',
            'fine'            => 'nullable|numeric|min:0',
            'payment_mode'    => [
                'required', 'string',
                \Illuminate\Validation\Rule::exists('payment_methods', 'code')
                    ->where('school_id', $school->id)
                    ->where('is_active', true),
            ],
            'payment_date'    => 'required|date|before_or_equal:today',
            'transaction_ref' => 'nullable|string|max:100',
            'remarks'         => 'nullable|string',
        ]);

        $amountDue  = (float) $validated['amount_due'];
        $amountPaid = (float) $validated['amount_paid'];
        $discount   = (float) ($validated['discount'] ?? 0);
        $fine       = (float) ($validated['fine'] ?? 0);

        // Tax calculation for taxable heads (matches web collectStore)
        $feeHead = \App\Models\FeeHead::find($validated['fee_head_id']);
        $taxableAmount = $amountPaid;
        $taxAmount     = 0.00;
        $taxPercent    = 0.00;
        if ($feeHead && $feeHead->is_taxable && $feeHead->gst_percent > 0) {
            $taxPercent    = (float) $feeHead->gst_percent;
            $taxableAmount = round($amountPaid / (1 + ($taxPercent / 100)), 2);
            $taxAmount     = round($amountPaid - $taxableAmount, 2);
        }

        $balance = max(0, $amountDue - $discount + $fine - $amountPaid);
        $status  = $balance <= 0 ? 'paid' : ($amountPaid > 0 ? 'partial' : 'due');

        $payment = FeePayment::create([
            'school_id'        => $school->id,
            'student_id'       => $validated['student_id'],
            'academic_year_id' => $yearId,
            'fee_head_id'      => $validated['fee_head_id'],
            'term'             => $validated['term'],
            'amount_due'       => $amountDue,
            'amount_paid'      => $amountPaid,
            'discount'         => $discount,
            'fine'             => $fine,
            'balance'          => $balance,
            'taxable_amount'   => $taxableAmount,
            'tax_amount'       => $taxAmount,
            'tax_percent'      => $taxPercent,
            'payment_mode'     => $validated['payment_mode'],
            'payment_date'     => $validated['payment_date'],
            'transaction_ref'  => $validated['transaction_ref'] ?? null,
            'status'           => $status,
            'remarks'          => $validated['remarks'] ?? null,
            'collected_by'     => $request->user()->id,
        ]);

        // Trigger notification (same as web flow)
        try {
            (new \App\Services\NotificationService($school))->notifyFeePayment($payment);
        } catch (\Throwable $e) {
            // Don't fail the collection if notify throws
        }

        return response()->json([
            'success'     => true,
            'message'     => "Payment recorded. Balance: ₹{$balance}",
            'payment_id'  => $payment->id,
            'receipt_no'  => $payment->receipt_no,
            'balance'     => $balance,
            'status'      => $status,
        ], 201);
    }

    /**
     * GET /mobile/finance/due-report
     * Lists students with outstanding fee balance for the current academic year.
     * Mirrors the web Due Report (LedgerController::dueReport) but returns a
     * paginated JSON response and, by default, only students whose combined
     * regular + transport + hostel + stationary balance is greater than zero.
     *
     * Query params (all optional):
     *   class_id, section_id
     *   status               'all' | 'defaulter' | 'not_defaulter'  (default 'all')
     *   fee_types[]          subset of ['regular','transport','hostel','stationary']
     *   student_type         'new' | 'old'
     *   sort                 'balance' | 'name' | 'class'  (default 'balance')
     *   order                'asc' | 'desc'                (default 'desc')
     *   per_page             5..100                        (default 30)
     *   page                 >=1                           (default 1)
     *   include_zero_balance '1' to also return students with total_balance == 0
     */
    public function dueReport(Request $request, \App\Services\DueReportService $service): JsonResponse
    {
        $this->assertCanCollectFees($request);

        $school = app('current_school');
        $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        if (!$yearId) {
            return response()->json(['error' => 'No active academic year.'], 422);
        }

        $status = in_array($request->input('status'), ['all', 'defaulter', 'not_defaulter'], true)
            ? $request->input('status') : 'all';

        $feeTypesIn = $request->input('fee_types', []);
        if (is_string($feeTypesIn)) {
            $feeTypesIn = array_filter(array_map('trim', explode(',', $feeTypesIn)));
        }
        $feeTypes = array_values(array_intersect(
            ['regular', 'transport', 'hostel', 'stationary'],
            (array) $feeTypesIn
        ));

        $studentType = in_array($request->input('student_type'), ['new', 'old'], true)
            ? $request->input('student_type') : null;

        $rows = $service->rowsFor(
            $school->id,
            $yearId,
            $request->filled('class_id')   ? (int) $request->class_id   : null,
            $request->filled('section_id') ? (int) $request->section_id : null,
            $status,
            $feeTypes,
            null,
            $studentType,
        );

        // Due report semantics: only students with an outstanding balance unless
        // the caller explicitly asks for everyone (e.g. for a "paid" view).
        if (!$request->boolean('include_zero_balance')) {
            $rows = array_values(array_filter($rows, fn($r) => $r['total_balance'] > 0));
        }

        $sort  = in_array($request->input('sort'), ['balance', 'name', 'class'], true)
            ? $request->input('sort') : 'balance';
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        usort($rows, function ($a, $b) use ($sort, $order) {
            $cmp = match ($sort) {
                'name'  => strcmp($a['name'],  $b['name']),
                'class' => strcmp($a['class'], $b['class']),
                default => $a['total_balance'] <=> $b['total_balance'],
            };
            return $order === 'asc' ? $cmp : -$cmp;
        });

        $perPage  = max(5, min(100, (int) $request->input('per_page', 30)));
        $page     = max(1, (int) $request->input('page', 1));
        $total    = count($rows);
        $items    = array_slice($rows, ($page - 1) * $perPage, $perPage);
        $lastPage = max(1, (int) ceil($total / $perPage));

        $totalOutstanding = array_sum(array_column($rows, 'total_balance'));
        $defaulterCount   = count(array_filter($rows, fn($r) => $r['is_defaulter']));

        $classes = \App\Models\CourseClass::where('school_id', $school->id)
            ->orderBy('numeric_value')->orderBy('name')
            ->with(['sections' => fn($q) => $q->orderBy('name')])
            ->get()
            ->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'sections' => $c->sections->map(fn($s) => [
                    'id'   => $s->id,
                    'name' => $s->name,
                ])->values(),
            ])->values();

        return response()->json([
            'data'         => $items,
            'current_page' => $page,
            'last_page'    => $lastPage,
            'total'        => $total,
            'per_page'     => $perPage,
            'summary'      => [
                'total_outstanding' => round($totalOutstanding, 2),
                'defaulter_count'   => $defaulterCount,
                'total_students'    => $total,
            ],
            'filters' => [
                'classes' => $classes,
                'applied' => [
                    'class_id'     => $request->input('class_id'),
                    'section_id'   => $request->input('section_id'),
                    'status'       => $status,
                    'fee_types'    => $feeTypes,
                    'student_type' => $studentType,
                    'sort'         => $sort,
                    'order'        => $order,
                ],
            ],
        ]);
    }

    /**
     * GET /mobile/attendance/report?class_id=X&section_id=Y&month=YYYY-MM
     * Returns per-student day-by-day map + counts, matching web AttendanceController::report().
     */
    public function attendanceReport(Request $request): JsonResponse
    {
        $user      = $request->user();
        $school    = app('current_school');
        $yearId    = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $classId   = $request->input('class_id');
        $sectionId = $request->input('section_id');
        $month     = $request->input('month', now()->format('Y-m'));

        if (!$classId) {
            return response()->json(['message' => 'class_id is required.'], 422);
        }

        // Teachers: verify they have access to the requested class/section
        if ($user->isTeacher()) {
            $scope = app(TeacherScopeService::class)->for($user);
            if ($scope->restricted) {
                if (! $scope->classIds->contains((int) $classId)) {
                    return response()->json(['message' => 'You are not authorized to view this class.'], 403);
                }
                if ($sectionId && ! $scope->sectionIds->contains((int) $sectionId)) {
                    return response()->json(['message' => 'You are not authorized to view this section.'], 403);
                }
            }
        }

        // Sanitize month format
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }

        [$year, $mon] = explode('-', $month);
        $from        = "{$year}-{$mon}-01";
        $monthDate   = \Carbon\Carbon::parse($from);
        $to          = $monthDate->copy()->endOfMonth()->toDateString();
        $daysInMonth = $monthDate->daysInMonth;

        // Match web: status='current', order by roll_no then id
        $histories = \App\Models\StudentAcademicHistory::where('school_id', $school->id)
            ->when($yearId, fn($q) => $q->where('academic_year_id', $yearId))
            ->where('class_id', $classId)
            ->where('status', 'current')
            ->when($sectionId, fn($q) => $q->where('section_id', $sectionId))
            ->with('student:id,first_name,last_name')
            ->orderBy('roll_no')
            ->orderBy('id')
            ->get();

        $studentIds = $histories->pluck('student_id');

        $records = \App\Models\Attendance::where('school_id', $school->id)
            ->whereIn('student_id', $studentIds)
            ->whereBetween('date', [$from, $to])
            ->get(['student_id', 'date', 'status'])
            ->groupBy('student_id');

        $report       = [];
        $totalPresent = 0;
        $totalAbsent  = 0;
        $totalLate    = 0;
        $markedDays   = 0;

        foreach ($histories as $h) {
            if (!$h->student) continue;

            $sid    = $h->student->id;
            $recs   = $records->get($sid, collect());
            $days   = [];
            $counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'half_day' => 0, 'leave' => 0, 'working_days' => 0];

            foreach ($recs as $rec) {
                // Handle both string and backed-enum status
                $status = $rec->status instanceof \BackedEnum ? $rec->status->value : (string) $rec->status;
                $day    = (int) \Carbon\Carbon::parse($rec->date)->day;
                $days[$day] = $status;
                if (array_key_exists($status, $counts)) {
                    $counts[$status]++;
                }
                $counts['working_days']++;
            }

            $totalPresent      += $counts['present'];
            $totalAbsent       += $counts['absent'];
            $totalLate         += $counts['late'];
            $markedDays        += $counts['working_days'];

            // Percentage: (present + late×0.5 + half_day×0.5) / working_days × 100 (matches web)
            $effectivePresent = $counts['present'] + ($counts['late'] * 0.5) + ($counts['half_day'] * 0.5);
            $pct = $counts['working_days'] > 0
                ? round($effectivePresent / $counts['working_days'] * 100, 1)
                : null;

            $report[] = [
                'student_id' => $sid,
                'name'       => trim(($h->student->first_name ?? '') . ' ' . ($h->student->last_name ?? '')),
                'roll_no'    => $h->roll_no,
                'days'       => $days,
                'counts'     => $counts,
                'percentage' => $pct,
            ];
        }

        $totalStudents = count($report);
        $summary = [
            'total_students' => $totalStudents,
            'total_absent'   => $totalAbsent,
            'total_late'     => $totalLate,
            'avg_present'    => ($totalStudents > 0 && $markedDays > 0)
                ? round($totalPresent / max(1, $markedDays / max(1, $totalStudents)))
                : 0,
        ];

        return response()->json([
            'report'        => $report,
            'days_in_month' => $daysInMonth,
            'month'         => $month,
            'summary'       => $summary,
        ]);
    }

    /**
     * GET /mobile/attendance/admin/date-wise
     *
     * Daily attendance summary across classes for a date range. Mirrors the
     * web AttendanceController::dateWise() in shape but is leaner — returns
     * the same per-date totals and per-class breakdown so the mobile UI can
     * render a "What happened on each day" overview without a second
     * round trip per day.
     *
     * Query params:
     *   from         YYYY-MM-DD (default: 7 days ago)
     *   to           YYYY-MM-DD (default: today)
     *   class_id     restrict to one class
     *   section_id   restrict to one section
     *
     * The range is capped at 31 days to keep payloads small on mobile.
     */
    public function attendanceDateWise(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        if (!in_array($type, ['admin', 'school_admin', 'principal', 'super_admin', 'teacher', 'staff'], true)) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $school   = app('current_school');
        $schoolId = $school->id;
        $yearId   = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $to   = $request->input('to',   now()->toDateString());
        $from = $request->input('from', now()->subDays(6)->toDateString());

        try {
            $fromDate = \Carbon\Carbon::parse($from);
            $toDate   = \Carbon\Carbon::parse($to);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid date range.'], 422);
        }
        if ($fromDate->gt($toDate)) {
            [$from, $to] = [$to, $from];
            $fromDate = \Carbon\Carbon::parse($from);
            $toDate   = \Carbon\Carbon::parse($to);
        }
        if ($fromDate->diffInDays($toDate) > 31) {
            $fromDate = $toDate->copy()->subDays(31);
            $from     = $fromDate->toDateString();
        }

        $classId   = $request->input('class_id');
        $sectionId = $request->input('section_id');

        // Teacher scope — mirror the existing report endpoint's behaviour
        if ($user->isTeacher()) {
            $scope = app(TeacherScopeService::class)->for($user);
            if ($scope->restricted) {
                if ($classId && !$scope->classIds->contains((int) $classId)) {
                    return response()->json(['error' => 'You are not authorized to view this class.'], 403);
                }
                if ($sectionId && !$scope->sectionIds->contains((int) $sectionId)) {
                    return response()->json(['error' => 'You are not authorized to view this section.'], 403);
                }
            }
        }

        $normDate = fn($d) => \Carbon\Carbon::parse($d)->toDateString();

        // Per-day aggregate counts by status
        $dayStatusCounts = Attendance::where('school_id', $schoolId)
            ->when($yearId,    fn($q) => $q->where('academic_year_id', $yearId))
            ->when($classId,   fn($q) => $q->where('class_id',   $classId))
            ->when($sectionId, fn($q) => $q->where('section_id', $sectionId))
            ->whereBetween('date', [$from, $to])
            ->selectRaw('date, status, COUNT(*) as cnt')
            ->groupBy('date', 'status')
            ->get()
            ->groupBy(fn($r) => $normDate($r->date));

        // Per-day per-class counts
        $dayClassCounts = Attendance::where('school_id', $schoolId)
            ->when($yearId,    fn($q) => $q->where('academic_year_id', $yearId))
            ->when($classId,   fn($q) => $q->where('class_id',   $classId))
            ->when($sectionId, fn($q) => $q->where('section_id', $sectionId))
            ->whereBetween('date', [$from, $to])
            ->selectRaw('date, class_id, status, COUNT(*) as cnt')
            ->groupBy('date', 'class_id', 'status')
            ->get()
            ->groupBy(fn($r) => $normDate($r->date));

        // Class names for breakdown labels
        $classNames = \App\Models\CourseClass::where('school_id', $schoolId)
            ->orderBy('numeric_value')->orderBy('name')
            ->pluck('name', 'id');

        // Per-class enrollment for "% present" computation
        $classEnrollment = $yearId
            ? StudentAcademicHistory::where('school_id', $schoolId)
                ->where('academic_year_id', $yearId)
                ->when($classId,   fn($q) => $q->where('class_id',   $classId))
                ->when($sectionId, fn($q) => $q->where('section_id', $sectionId))
                ->where('status', 'current')
                ->selectRaw('class_id, COUNT(*) as enrolled')
                ->groupBy('class_id')
                ->pluck('enrolled', 'class_id')
                ->toArray()
            : [];
        $totalEnrolled = array_sum($classEnrollment);

        // Build a date list (descending — newest day first feels natural in a list)
        $dates = [];
        $cursor = $toDate->copy();
        while ($cursor->gte($fromDate)) {
            $dates[] = $cursor->toDateString();
            $cursor->subDay();
        }

        $extract = function ($rows, string $status): int {
            return (int) ($rows->where('status', $status)->first()?->cnt ?? 0);
        };

        $data = [];
        foreach ($dates as $d) {
            $statusRows = $dayStatusCounts->get($d, collect());
            $present  = $extract($statusRows, 'present');
            $absent   = $extract($statusRows, 'absent');
            $late     = $extract($statusRows, 'late');
            $halfDay  = $extract($statusRows, 'half_day');
            $leave    = $extract($statusRows, 'leave');
            $marked   = $present + $absent + $late + $halfDay + $leave;

            $unmarked = max(0, $totalEnrolled - $marked);
            $pct      = $totalEnrolled > 0 ? round(($present / $totalEnrolled) * 100, 1) : null;

            // Class breakdown
            $classRowsForDay = $dayClassCounts->get($d, collect())->groupBy('class_id');
            $classes = [];
            foreach ($classRowsForDay as $cid => $rows) {
                $cP = $extract($rows, 'present');
                $cA = $extract($rows, 'absent');
                $cL = $extract($rows, 'late');
                $cH = $extract($rows, 'half_day');
                $cV = $extract($rows, 'leave');
                $cEnrolled = (int) ($classEnrollment[$cid] ?? ($cP + $cA + $cL + $cH + $cV));
                $cPct = $cEnrolled > 0 ? round(($cP / $cEnrolled) * 100, 1) : null;
                $classes[] = [
                    'class_id'   => (int) $cid,
                    'class_name' => $classNames[$cid] ?? '—',
                    'enrolled'   => $cEnrolled,
                    'present'    => $cP,
                    'absent'     => $cA,
                    'late'       => $cL,
                    'half_day'   => $cH,
                    'leave'      => $cV,
                    'percent'    => $cPct,
                ];
            }
            // Sort breakdown by class number-ish ordering — fall back to name
            usort($classes, fn($a, $b) => strcasecmp($a['class_name'], $b['class_name']));

            $data[] = [
                'date'    => $d,
                'summary' => [
                    'enrolled' => $totalEnrolled,
                    'marked'   => $marked,
                    'unmarked' => $unmarked,
                    'present'  => $present,
                    'absent'   => $absent,
                    'late'     => $late,
                    'half_day' => $halfDay,
                    'leave'    => $leave,
                    'percent'  => $pct,
                ],
                'classes' => $classes,
            ];
        }

        return response()->json([
            'data'    => $data,
            'applied' => [
                'from'       => $from,
                'to'         => $to,
                'class_id'   => $classId,
                'section_id' => $sectionId,
                'days'       => count($dates),
            ],
            'filters' => [
                'classes' => $classNames->map(fn($name, $id) => [
                    'id' => (int) $id, 'name' => $name,
                ])->values(),
            ],
        ]);
    }

    // ── AI Insights ───────────────────────────────────────────────────────────

    public function aiInsights(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return app(\App\Http\Controllers\School\AiInsightsController::class)
            ->generateInsights($request);
    }

    public function aiQuery(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return app(\App\Http\Controllers\School\AiInsightsController::class)
            ->queryData($request);
    }

    // ── Academic Create Endpoints ────────────────────────────────────────────

    /**
     * Return merged subjects (class-level + section-level) for a given class.
     * Used by mobile create forms to populate subject picker.
     */
    public function subjectsForClass(Request $request): JsonResponse
    {
        $school  = app('current_school');
        $classId = $request->get('class_id');
        if (!$classId) return response()->json(['subjects' => []]);

        // Use ClassSubject pivot model — most reliable; covers class-level and
        // section-level subject assignments in one query.
        $subjects = \App\Models\ClassSubject::where('school_id', $school->id)
            ->where('course_class_id', $classId)
            ->with('subject:id,name')
            ->get()
            ->pluck('subject')
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn($s) => ['id' => $s->id, 'name' => $s->name]);

        return response()->json(['subjects' => $subjects]);
    }

    /** POST /mobile/diary — Teacher/admin creates a diary entry. */
    public function storeDiary(Request $request): JsonResponse
    {
        $user    = $request->user();
        $school  = app('current_school');
        $yearId  = app('current_academic_year_id');
        $staffId = $user->staff?->id;
        $isAdmin = in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin']);
        if (!$isAdmin && !$staffId) return response()->json(['message' => 'Unauthorized.'], 403);

        $data = $request->validate([
            'class_id'      => 'required|exists:course_classes,id',
            'section_id'    => 'required|exists:sections,id',
            'subject_id'    => 'required|exists:subjects,id',
            'date'          => 'required|date',
            'content'       => 'required|string|max:5000',
            'attachments'   => 'nullable|array',
            'attachments.*' => 'nullable|file|max:10240|mimes:pdf,ppt,pptx,doc,docx,jpg,jpeg,png,zip',
        ]);

        // Scope guard: non-admins can only create diary entries for sections/subjects they teach
        if (! $isAdmin) {
            $scope = app(\App\Services\TeacherScopeService::class)->for($user);
            if ($scope->restricted) {
                $entry = $scope->allowedMap[(int)$data['class_id']][(int)$data['section_id']] ?? null;
                if ($entry === null) {
                    return response()->json(['message' => 'Not assigned to this class/section.'], 403);
                }
                if (is_array($entry) && ! in_array((int)$data['subject_id'], $entry)) {
                    return response()->json(['message' => 'Not assigned to teach this subject in this section.'], 403);
                }
            }
        }

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachments[] = $file->store('academic/diary', 'public');
            }
        }

        $diary = \App\Models\StudentDiary::create([
            'school_id'        => $school->id,
            'academic_year_id' => $yearId,
            'class_id'         => $data['class_id'],
            'section_id'       => $data['section_id'],
            'subject_id'       => $data['subject_id'],
            'teacher_id'       => $staffId,
            'date'             => $data['date'],
            'content'          => strip_tags($data['content']),
            'attachments'      => $attachments,
        ]);

        try { (new \App\Services\NotificationService($school))->notifyDiary($diary); }
        catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('Diary push failed: ' . $e->getMessage()); }

        return response()->json(['message' => 'Diary entry created.', 'id' => $diary->id], 201);
    }

    /** POST /mobile/assignments — Teacher/admin creates an assignment. */
    public function storeAssignment(Request $request): JsonResponse
    {
        $user    = $request->user();
        $school  = app('current_school');
        $yearId  = app('current_academic_year_id');
        $staffId = $user->staff?->id;
        $isAdmin = in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin']);
        if (!$isAdmin && !$staffId) return response()->json(['message' => 'Unauthorized.'], 403);

        $data = $request->validate([
            'class_id'      => 'required|exists:course_classes,id',
            'section_ids'   => 'required|array|min:1',
            'section_ids.*' => 'required|exists:sections,id',
            'subject_id'    => 'required|exists:subjects,id',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'due_date'      => 'required|date',
            'max_marks'     => 'required|integer|min:1|max:9999',
            'status'        => 'nullable|in:draft,published',
            'attachments'   => 'nullable|array',
            'attachments.*' => 'nullable|file|max:10240|mimes:pdf,ppt,pptx,doc,docx,jpg,jpeg,png,zip',
        ]);

        // Scope guard: non-admins can only create assignments for sections/subjects they teach
        if (! $isAdmin) {
            $scope = app(\App\Services\TeacherScopeService::class)->for($user);
            if ($scope->restricted) {
                foreach ($data['section_ids'] as $sectionId) {
                    $entry = $scope->allowedMap[(int)$data['class_id']][(int)$sectionId] ?? null;
                    if ($entry === null) {
                        return response()->json(['message' => 'Not assigned to section ' . $sectionId . '.'], 403);
                    }
                    if (is_array($entry) && ! in_array((int)$data['subject_id'], $entry)) {
                        return response()->json(['message' => 'Not assigned to teach this subject in section ' . $sectionId . '.'], 403);
                    }
                }
            }
        }

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachments[] = $file->store('academic/assignments', 'public');
            }
        }

        $ids = [];
        foreach ($data['section_ids'] as $sectionId) {
            $a = \App\Models\Assignment::create([
                'school_id'        => $school->id,
                'academic_year_id' => $yearId,
                'class_id'         => $data['class_id'],
                'section_id'       => $sectionId,
                'subject_id'       => $data['subject_id'],
                'teacher_id'       => $staffId,
                'title'            => $data['title'],
                'description'      => $data['description'] ?? null,
                'due_date'         => $data['due_date'],
                'max_marks'        => $data['max_marks'],
                'status'           => $data['status'] ?? 'published',
                'attachments'      => $attachments,
            ]);
            $ids[] = $a->id;
            try { (new \App\Services\NotificationService($school))->notifyAssignment($a); }
            catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('Assignment push failed: ' . $e->getMessage()); }
        }

        return response()->json(['message' => 'Assignment created for ' . count($ids) . ' section(s).'], 201);
    }

    /** POST /mobile/syllabus/topics — Teacher/admin adds a syllabus topic. */
    public function storeSyllabusTopic(Request $request): JsonResponse
    {
        $user    = $request->user();
        $school  = app('current_school');
        $staffId = $user->staff?->id;
        $isAdmin = in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin']);
        if (!$isAdmin && !$staffId) return response()->json(['message' => 'Unauthorized.'], 403);

        $data = $request->validate([
            'class_id'     => 'required|exists:course_classes,id',
            'subject_id'   => 'required|exists:subjects,id',
            'chapter_name' => 'required|string|max:255',
            'topic_name'   => 'required|string|max:255',
            'sort_order'   => 'nullable|integer|min:1',
        ]);

        // Scope guard: non-admins can only add topics for subjects they teach in this class
        if (! $isAdmin) {
            $scope = app(\App\Services\TeacherScopeService::class)->for($user);
            if ($scope->restricted) {
                $classSections = $scope->allowedMap[(int)$data['class_id']] ?? null;
                $hasAccess = false;
                if ($classSections) {
                    foreach ($classSections as $subjects) {
                        if ($subjects === 'ALL' || (is_array($subjects) && in_array((int)$data['subject_id'], $subjects))) {
                            $hasAccess = true;
                            break;
                        }
                    }
                }
                if (! $hasAccess) {
                    return response()->json(['message' => 'Not assigned to teach this subject in this class.'], 403);
                }
            }
        }

        $topic = \App\Models\SyllabusTopic::create([
            'school_id'    => $school->id,
            'class_id'     => $data['class_id'],
            'subject_id'   => $data['subject_id'],
            'chapter_name' => $data['chapter_name'],
            'topic_name'   => $data['topic_name'],
            'sort_order'   => $data['sort_order'] ?? 1,
        ]);

        return response()->json(['message' => 'Topic added.', 'id' => $topic->id], 201);
    }

    /** POST /mobile/resources/material — Teacher/admin uploads a learning material. */
    public function storeMaterial(Request $request): JsonResponse
    {
        $user    = $request->user();
        $school  = app('current_school');
        $staffId = $user->staff?->id;
        $isAdmin = in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin']);
        if (!$isAdmin && !$staffId) return response()->json(['message' => 'Unauthorized.'], 403);

        $data = $request->validate([
            'class_id'      => 'required|exists:course_classes,id',
            'section_ids'   => 'required|array|min:1',
            'section_ids.*' => 'required|exists:sections,id',
            'subject_id'    => 'required|exists:subjects,id',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:1000',
            'type'          => 'required|in:pdf,doc,ppt,video,image,link,other',
            'chapter_name'  => 'nullable|string|max:255',
            'external_url'  => 'nullable|url|max:500',
            'is_published'  => 'nullable|boolean',
            'file'          => 'nullable|file|max:20480|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png,mp4,mov',
        ]);

        // Scope guard: non-admins can only upload materials for sections/subjects they teach
        if (! $isAdmin) {
            $scope = app(\App\Services\TeacherScopeService::class)->for($user);
            if ($scope->restricted) {
                foreach ($data['section_ids'] as $sectionId) {
                    $entry = $scope->allowedMap[(int)$data['class_id']][(int)$sectionId] ?? null;
                    if ($entry === null) {
                        return response()->json(['message' => 'Not assigned to section ' . $sectionId . '.'], 403);
                    }
                    if (is_array($entry) && ! in_array((int)$data['subject_id'], $entry)) {
                        return response()->json(['message' => 'Not assigned to teach this subject in section ' . $sectionId . '.'], 403);
                    }
                }
            }
        }

        $filePath = null;
        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $filePath = $request->file('file')->store('academic/materials', 'public');
        }

        foreach ($data['section_ids'] as $sectionId) {
            \App\Models\LearningMaterial::create([
                'school_id'    => $school->id,
                'class_id'     => $data['class_id'],
                'section_id'   => $sectionId,
                'subject_id'   => $data['subject_id'],
                'teacher_id'   => $staffId,
                'title'        => $data['title'],
                'description'  => $data['description'] ?? null,
                'type'         => $data['type'],
                'chapter_name' => $data['chapter_name'] ?? null,
                'file_path'    => $filePath,
                'external_url' => $data['external_url'] ?? null,
                'is_published' => $data['is_published'] ?? false,
            ]);
        }

        return response()->json(['message' => 'Material uploaded to ' . count($data['section_ids']) . ' section(s).'], 201);
    }

    /** POST /mobile/book-list — Admin/teacher adds a book to the list. */
    public function storeBook(Request $request): JsonResponse
    {
        $user    = $request->user();
        $school  = app('current_school');
        $yearId  = app('current_academic_year_id');
        $staffId = $user->staff?->id;
        $isAdmin = in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin']);
        if (!$isAdmin && !$staffId) return response()->json(['message' => 'Unauthorized.'], 403);

        $data = $request->validate([
            'class_id'   => 'required|exists:course_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'book_name'  => 'required|string|max:255',
            'publisher'  => 'nullable|string|max:255',
            'author'     => 'nullable|string|max:255',
            'isbn'       => 'nullable|string|max:20',
        ]);

        $book = \App\Models\BookList::create([
            'school_id'        => $school->id,
            'academic_year_id' => $yearId,
            'class_id'         => $data['class_id'],
            'subject_id'       => $data['subject_id'],
            'book_name'        => $data['book_name'],
            'publisher'        => $data['publisher'] ?? null,
            'author'           => $data['author'] ?? null,
            'isbn'             => $data['isbn'] ?? null,
        ]);

        return response()->json(['message' => 'Book added to list.', 'id' => $book->id], 201);
    }

    // ── Staff Punch ────────────────────────────────────────────────────────────

    /**
     * GET /mobile/staff-punch/status
     * Returns today's punch status + last 7 days history + geofence config.
     * Available to all authenticated users who have a staff record.
     */
    public function staffPunchStatus(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $staff = Staff::where('user_id', $user->id)
                      ->where('school_id', $school->id)
                      ->first();

        if (! $staff) {
            return response()->json(['message' => 'No staff record for this user.'], 404);
        }

        $today = now()->toDateString();

        $attendance = StaffAttendance::where('school_id', $school->id)
            ->where('staff_id', $staff->id)
            ->whereDate('date', $today)
            ->first();

        $history = StaffAttendance::where('school_id', $school->id)
            ->where('staff_id', $staff->id)
            ->whereDate('date', '>=', now()->subDays(6)->toDateString())
            ->orderByDesc('date')
            ->get()
            ->map(fn ($r) => [
                'date'      => $r->date instanceof \Carbon\Carbon ? $r->date->toDateString() : (string) $r->date,
                'day'       => $r->date instanceof \Carbon\Carbon ? $r->date->format('D') : \Carbon\Carbon::parse($r->date)->format('D'),
                'status'    => $r->status,
                'check_in'  => $r->check_in  ? substr($r->check_in,  0, 5) : null,
                'check_out' => $r->check_out ? substr($r->check_out, 0, 5) : null,
            ])->values()->toArray();

        $geoFenceEnabled = $school->geo_fence_lat && $school->geo_fence_lng;

        return response()->json([
            'staff_id'    => $staff->id,
            'staff_name'  => trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? '')),
            'employee_id' => $staff->employee_id,
            'today'       => $today,
            'attendance'  => $attendance ? [
                'status'    => $attendance->status,
                'check_in'  => $attendance->check_in  ? substr($attendance->check_in,  0, 5) : null,
                'check_out' => $attendance->check_out ? substr($attendance->check_out, 0, 5) : null,
            ] : null,
            'history'  => $history,
            'geo_fence' => [
                'enabled' => $geoFenceEnabled,
                'lat'     => $geoFenceEnabled ? (float) $school->geo_fence_lat    : null,
                'lng'     => $geoFenceEnabled ? (float) $school->geo_fence_lng    : null,
                'radius'  => $geoFenceEnabled ? (int)   $school->geo_fence_radius : null,
            ],
        ]);
    }

    /**
     * POST /mobile/staff-punch/clock-in
     * Body: { latitude, longitude }
     */
    public function staffPunchClockIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'remarks'   => 'nullable|string|max:255',
        ]);

        $user   = $request->user();
        $school = app('current_school');

        $staff = Staff::where('user_id', $user->id)
                      ->where('school_id', $school->id)
                      ->first();

        if (! $staff) {
            return response()->json(['message' => 'No staff record for this user.'], 404);
        }

        // Geofence check
        if ($school->geo_fence_lat && $school->geo_fence_lng) {
            $distance = $this->staffHaversine(
                $validated['latitude'], $validated['longitude'],
                (float) $school->geo_fence_lat, (float) $school->geo_fence_lng
            );

            if ($distance > (int) $school->geo_fence_radius) {
                $dist = $distance >= 1000
                    ? round($distance / 1000, 1) . ' km'
                    : round($distance) . 'm';
                return response()->json([
                    'message' => "You are {$dist} away from school. Please punch within {$school->geo_fence_radius}m of campus.",
                    'error'   => 'geofence',
                    'distance_metres' => (int) round($distance),
                ], 422);
            }
        }

        $today = now()->toDateString();

        $existing = StaffAttendance::where('school_id', $school->id)
            ->where('staff_id', $staff->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing && $existing->check_in) {
            return response()->json(['message' => 'You have already clocked in today.', 'error' => 'duplicate'], 422);
        }

        // Determine late vs present
        $lateThreshold = $school->settings['late_threshold'] ?? '09:30';
        $now           = now()->format('H:i:s');
        $status        = Carbon::parse($now)->gt(Carbon::parse($lateThreshold)) ? 'late' : 'present';

        StaffAttendance::updateOrCreate(
            ['school_id' => $school->id, 'staff_id' => $staff->id, 'date' => $today],
            array_filter([
                'status'       => $status,
                'check_in'     => $now,
                'punch_in_lat' => $validated['latitude'],
                'punch_in_lng' => $validated['longitude'],
                'remarks'      => $validated['remarks'] ?? null,
                'marked_by'    => $user->id,
            ], fn($v) => $v !== null)
        );

        $message = $status === 'late' ? 'Clocked in — marked late.' : 'Clocked in successfully!';

        return response()->json([
            'message'  => $message,
            'status'   => $status,
            'check_in' => substr($now, 0, 5),
        ]);
    }

    /**
     * POST /mobile/staff-punch/clock-out
     * Body: { latitude, longitude }
     */
    public function staffPunchClockOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'remarks'   => 'nullable|string|max:255',
        ]);

        $user   = $request->user();
        $school = app('current_school');

        $staff = Staff::where('user_id', $user->id)
                      ->where('school_id', $school->id)
                      ->first();

        if (! $staff) {
            return response()->json(['message' => 'No staff record for this user.'], 404);
        }

        // Geofence check
        if ($school->geo_fence_lat && $school->geo_fence_lng) {
            $distance = $this->staffHaversine(
                $validated['latitude'], $validated['longitude'],
                (float) $school->geo_fence_lat, (float) $school->geo_fence_lng
            );

            if ($distance > (int) $school->geo_fence_radius) {
                $dist = $distance >= 1000
                    ? round($distance / 1000, 1) . ' km'
                    : round($distance) . 'm';
                return response()->json([
                    'message' => "You are {$dist} away from school. Please punch within {$school->geo_fence_radius}m of campus.",
                    'error'   => 'geofence',
                    'distance_metres' => (int) round($distance),
                ], 422);
            }
        }

        $today = now()->toDateString();

        $attendance = StaffAttendance::where('school_id', $school->id)
            ->where('staff_id', $staff->id)
            ->whereDate('date', $today)
            ->first();

        if (! $attendance || ! $attendance->check_in) {
            return response()->json(['message' => 'You must clock in before clocking out.', 'error' => 'no_clock_in'], 422);
        }

        if ($attendance->check_out) {
            return response()->json(['message' => 'You have already clocked out today.', 'error' => 'duplicate'], 422);
        }

        $now = now()->format('H:i:s');

        $update = [
            'check_out'      => $now,
            'punch_out_lat'  => $validated['latitude'],
            'punch_out_lng'  => $validated['longitude'],
        ];
        // Append clock-out remark to existing remark if both present
        if (!empty($validated['remarks'])) {
            $update['remarks'] = trim(
                ($attendance->remarks ? $attendance->remarks . ' · ' : '') . $validated['remarks']
            );
        }
        $attendance->update($update);

        return response()->json([
            'message'   => 'Clocked out successfully!',
            'check_out' => substr($now, 0, 5),
        ]);
    }

    // ── Student Leave Management (approve/reject) ─────────────────────────────

    /**
     * PATCH /mobile/leaves/{id}/approve
     * Admin or teacher approves a student leave request.
     */
    public function approveStudentLeave(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $isManager = in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin', 'teacher']);
        if (!$isManager) {
            return response()->json(['message' => 'Only admin or teachers can approve student leaves.'], 403);
        }

        $leave = StudentLeave::where('school_id', $school->id)->findOrFail($id);

        // Teachers: verify the student belongs to their assigned section
        if ($user->isTeacher()) {
            $scope = app(TeacherScopeService::class)->for($user);
            if ($scope->restricted) {
                $studentSection = StudentAcademicHistory::where('student_id', $leave->student_id)
                    ->where('school_id', $school->id)
                    ->orderByDesc('id')->value('section_id');
                if (! $scope->sectionIds->contains($studentSection)) {
                    return response()->json(['message' => 'You are not authorized to approve this leave.'], 403);
                }
            }
        }

        if ($leave->status !== 'pending') {
            return response()->json(['message' => 'Leave is not in pending state.'], 422);
        }

        $validated = $request->validate(['remarks' => 'nullable|string|max:500']);

        $leave->update([
            'status'      => 'approved',
            'approved_by' => $user->id,
            'remarks'     => $validated['remarks'] ?? null,
        ]);

        return response()->json(['success' => true, 'message' => 'Leave approved.']);
    }

    /**
     * PATCH /mobile/leaves/{id}/reject
     * Admin or teacher rejects a student leave request.
     */
    public function rejectStudentLeave(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $isManager = in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin', 'teacher']);
        if (!$isManager) {
            return response()->json(['message' => 'Only admin or teachers can reject student leaves.'], 403);
        }

        $leave = StudentLeave::where('school_id', $school->id)->findOrFail($id);

        // Teachers: verify the student belongs to their assigned section
        if ($user->isTeacher()) {
            $scope = app(TeacherScopeService::class)->for($user);
            if ($scope->restricted) {
                $studentSection = StudentAcademicHistory::where('student_id', $leave->student_id)
                    ->where('school_id', $school->id)
                    ->orderByDesc('id')->value('section_id');
                if (! $scope->sectionIds->contains($studentSection)) {
                    return response()->json(['message' => 'You are not authorized to reject this leave.'], 403);
                }
            }
        }

        if ($leave->status !== 'pending') {
            return response()->json(['message' => 'Leave is not in pending state.'], 422);
        }

        $validated = $request->validate(['remarks' => 'nullable|string|max:500']);

        $leave->update([
            'status'      => 'rejected',
            'approved_by' => $user->id,
            'remarks'     => $validated['remarks'] ?? null,
        ]);

        return response()->json(['success' => true, 'message' => 'Leave rejected.']);
    }

    // ── Staff Leave Management ─────────────────────────────────────────────────

    /**
     * GET /mobile/staff-leave-types
     * Leave types applicable to staff + balance for current user.
     */
    public function staffLeaveTypes(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');
        $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $types = \App\Models\LeaveType::where('school_id', $school->id)
            ->forStaff()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'color', 'days_allowed', 'requires_document', 'min_notice_days']);

        // Calculate days used per type for the current user this academic year
        $usedQuery = Leave::where('school_id', $school->id)
            ->where('user_id', $user->id)
            ->where('status', '!=', 'rejected');

        if ($yearId) {
            $academicYear = \App\Models\AcademicYear::find($yearId);
            if ($academicYear) {
                $usedQuery->whereDate('start_date', '>=', $academicYear->start_date)
                          ->whereDate('start_date', '<=', $academicYear->end_date);
            }
        }

        $usedLeaves = $usedQuery->get(['leave_type_id', 'start_date', 'end_date']);

        // PHP-side day counting (cross-DB compatible)
        $usedByType = [];
        foreach ($usedLeaves as $leave) {
            $days = Carbon::parse($leave->start_date)->diffInDays(Carbon::parse($leave->end_date)) + 1;
            $usedByType[$leave->leave_type_id] = ($usedByType[$leave->leave_type_id] ?? 0) + $days;
        }

        $balance = [];
        foreach ($types as $type) {
            $used = (int) ($usedByType[$type->id] ?? 0);
            $balance[$type->id] = [
                'allowed'   => $type->days_allowed,
                'used'      => $used,
                'remaining' => max(0, $type->days_allowed - $used),
            ];
        }

        return response()->json(['leave_types' => $types, 'balance' => $balance]);
    }

    /**
     * GET /mobile/staff-leaves
     * Admin sees all staff leaves; others see their own.
     */
    public function staffLeaves(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $isAdmin = in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin']);

        $query = Leave::where('school_id', $school->id)
            ->with(['leaveType:id,name,code,color', 'approver:id,name', 'user:id,name'])
            ->orderByDesc('created_at');

        if (!$isAdmin) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        $leaves = $query->paginate(20);

        $summaryQuery = Leave::where('school_id', $school->id);
        if (!$isAdmin) {
            $summaryQuery->where('user_id', $user->id);
        }

        $statusCounts = (clone $summaryQuery)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $summary = [
            'total'    => $statusCounts->sum(),
            'pending'  => $statusCounts['pending']  ?? 0,
            'approved' => $statusCounts['approved'] ?? 0,
            'rejected' => $statusCounts['rejected'] ?? 0,
        ];

        $dateFmt = $school->dateFmt();
        $leavesData = collect($leaves->items())->map(function ($l) use ($dateFmt) {
            $arr = $l->toArray();
            $arr['start_date_display'] = $l->start_date instanceof \Carbon\Carbon
                ? $l->start_date->format($dateFmt) : ($l->start_date ? \Carbon\Carbon::parse($l->start_date)->format($dateFmt) : null);
            $arr['end_date_display']   = $l->end_date instanceof \Carbon\Carbon
                ? $l->end_date->format($dateFmt) : ($l->end_date ? \Carbon\Carbon::parse($l->end_date)->format($dateFmt) : null);
            return $arr;
        });

        return response()->json([
            'leaves'  => $leavesData,
            'summary' => $summary,
            'total'   => $leaves->total(),
            'page'    => $leaves->currentPage(),
        ]);
    }

    /**
     * POST /mobile/staff-leaves
     * Staff member applies for leave.
     */
    public function applyStaffLeave(Request $request): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $validated = $request->validate([
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('school_id', $school->id)],
            'start_date'    => 'required|date|after_or_equal:today',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'required|string|max:1000',
        ]);

        $leaveType = \App\Models\LeaveType::where('id', $validated['leave_type_id'])
            ->where('school_id', $school->id)
            ->forStaff()
            ->where('is_active', true)
            ->first();

        if (!$leaveType) {
            return response()->json(['message' => 'Invalid or inactive leave type.'], 422);
        }

        if ($leaveType->min_notice_days > 0) {
            $minDate = now()->addDays($leaveType->min_notice_days)->toDateString();
            if ($validated['start_date'] < $minDate) {
                return response()->json([
                    'message' => "This leave type requires at least {$leaveType->min_notice_days} day(s) advance notice.",
                    'errors'  => ['start_date' => ["Minimum {$leaveType->min_notice_days} day(s) notice required."]],
                ], 422);
            }
        }

        // Balance check — reject if this application would exceed days_allowed.
        // (days_allowed = 0 means unlimited, skip the check.)
        if ($leaveType->days_allowed > 0) {
            $requestedDays = Carbon::parse($validated['start_date'])
                ->diffInDays(Carbon::parse($validated['end_date'])) + 1;

            $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
            $usedQuery = Leave::where('school_id', $school->id)
                ->where('user_id', $user->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', '!=', 'rejected');
            if ($yearId) {
                $year = \App\Models\AcademicYear::find($yearId);
                if ($year) {
                    $usedQuery->whereDate('start_date', '>=', $year->start_date)
                              ->whereDate('start_date', '<=', $year->end_date);
                }
            }
            $usedDays = 0;
            foreach ($usedQuery->get(['start_date', 'end_date']) as $l) {
                $usedDays += Carbon::parse($l->start_date)->diffInDays(Carbon::parse($l->end_date)) + 1;
            }
            $remaining = max(0, $leaveType->days_allowed - $usedDays);

            if ($requestedDays > $remaining) {
                return response()->json([
                    'message' => "Insufficient leave balance. You have {$remaining} day(s) of {$leaveType->name} remaining; this request needs {$requestedDays}.",
                    'errors'  => ['leave_type_id' => ["Only {$remaining} day(s) remaining."]],
                    'balance' => ['allowed' => $leaveType->days_allowed, 'used' => $usedDays, 'remaining' => $remaining, 'requested' => $requestedDays],
                ], 422);
            }
        }

        $leave = Leave::create([
            'school_id'     => $school->id,
            'user_id'       => $user->id,
            'leave_type_id' => $validated['leave_type_id'],
            'leave_type'    => strtolower($leaveType->code ?? $leaveType->name ?? 'other'),
            'start_date'    => $validated['start_date'],
            'end_date'      => $validated['end_date'],
            'reason'        => $validated['reason'],
            'status'        => 'pending',
        ]);

        $leave->load(['leaveType:id,name,code,color', 'user:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Leave application submitted successfully.',
            'leave'   => $leave,
        ], 201);
    }

    /**
     * DELETE /mobile/staff-leaves/{id}
     * Staff member cancels their own pending leave.
     */
    public function cancelStaffLeave(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $leave = Leave::where('school_id', $school->id)->findOrFail($id);

        if ($leave->user_id !== $user->id) {
            return response()->json(['message' => 'You can only cancel your own leave.'], 403);
        }
        if ($leave->status !== 'pending') {
            return response()->json(['message' => 'Only pending leaves can be cancelled.'], 422);
        }

        $leave->delete();

        return response()->json(['success' => true, 'message' => 'Leave application cancelled.']);
    }

    /**
     * PATCH /mobile/staff-leaves/{id}/approve
     * Admin approves a staff leave and auto-marks attendance.
     */
    public function approveStaffLeave(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $isAdmin = in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin']);
        if (!$isAdmin) {
            return response()->json(['message' => 'Only admins can approve staff leaves.'], 403);
        }

        $leave = Leave::where('school_id', $school->id)->findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json(['message' => 'Leave is not in pending state.'], 422);
        }

        $leave->update(['status' => 'approved', 'approved_by' => $user->id]);

        // Auto-mark StaffAttendance as 'leave' for each day (mirrors web controller)
        $staff = Staff::where('user_id', $leave->user_id)->where('school_id', $school->id)->first();
        if ($staff) {
            $leave->load('leaveType');
            $remark = 'Approved leave: ' . ($leave->leaveType->name ?? $leave->leave_type);
            $start  = Carbon::parse($leave->start_date);
            $end    = Carbon::parse($leave->end_date);
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                StaffAttendance::updateOrCreate(
                    ['school_id' => $school->id, 'staff_id' => $staff->id, 'date' => $date->toDateString()],
                    ['status' => 'leave', 'remarks' => $remark, 'marked_by' => $user->id]
                );
            }
        }

        return response()->json(['success' => true, 'message' => 'Leave approved and attendance updated.']);
    }

    /**
     * PATCH /mobile/staff-leaves/{id}/reject
     * Admin rejects a staff leave.
     */
    public function rejectStaffLeave(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $school = app('current_school');

        $isAdmin = in_array($user->user_type->value, ['admin', 'school_admin', 'principal', 'super_admin']);
        if (!$isAdmin) {
            return response()->json(['message' => 'Only admins can reject staff leaves.'], 403);
        }

        $leave = Leave::where('school_id', $school->id)->findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json(['message' => 'Leave is not in pending state.'], 422);
        }

        $leave->update(['status' => 'rejected', 'approved_by' => $user->id]);

        return response()->json(['success' => true, 'message' => 'Leave rejected.']);
    }

    /** Haversine distance in metres between two lat/lng points. */
    private function staffHaversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // ── Inventory ─────────────────────────────────────────────────────────

    public function inventoryAssets(Request $request): JsonResponse
    {
        $school = app('current_school');

        $query = \App\Models\Asset::where('school_id', $school->id)
            ->with('category:id,name');

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('category')) $query->where('category_id', $request->category);
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn($x) => $x->where('name', 'like', "%$q%")
                ->orWhere('asset_code', 'like', "%$q%")
                ->orWhere('serial_no', 'like', "%$q%"));
        }

        $assets = $query->latest()->paginate(50)->through(fn($a) => [
            'id'             => $a->id,
            'name'           => $a->name,
            'asset_code'     => $a->asset_code,
            'brand'          => $a->brand,
            'serial_no'      => $a->serial_no,
            'category'       => $a->category?->name,
            'purchase_cost'  => (float) $a->purchase_cost,
            'condition'      => $a->condition,
            'status'         => $a->status,
            'warranty_until' => $a->warranty_until,
        ]);

        return response()->json($assets);
    }

    public function inventoryAsset(Request $request, int $id): JsonResponse
    {
        $school = app('current_school');

        $asset = \App\Models\Asset::where('school_id', $school->id)
            ->with([
                'category:id,name',
                'activeAssignment',
                'maintenanceLogs' => fn($q) => $q->orderBy('reported_on', 'desc')->limit(10),
            ])
            ->findOrFail($id);

        return response()->json([
            'id'                  => $asset->id,
            'name'                => $asset->name,
            'asset_code'          => $asset->asset_code,
            'brand'               => $asset->brand,
            'model_no'            => $asset->model_no,
            'serial_no'           => $asset->serial_no,
            'category'            => $asset->category?->name,
            'purchase_date'       => $asset->purchase_date?->format('Y-m-d'),
            'purchase_cost'       => (float) $asset->purchase_cost,
            'current_value'       => round($asset->current_value, 2),
            'depreciation_method' => $asset->depreciation_method,
            'useful_life_years'   => $asset->useful_life_years,
            'condition'           => $asset->condition,
            'status'              => $asset->status,
            'warranty_until'      => $asset->warranty_until,
            'supplier'            => $asset->supplier,
            'notes'               => $asset->notes,
            'disposed_on'         => $asset->disposed_on?->format('Y-m-d'),
            'disposal_reason'     => $asset->disposal_reason,
            'active_assignment'   => $asset->activeAssignment ? [
                'location'      => $asset->activeAssignment->location,
                'assignee_type' => $asset->activeAssignment->assignee_type,
                'assignee_name' => $asset->activeAssignment->assignee_name,
                'assigned_on'   => $asset->activeAssignment->assigned_on?->format('Y-m-d'),
            ] : null,
            'recent_maintenance'  => $asset->maintenanceLogs->map(fn($m) => [
                'id'                => $m->id,
                'type'              => $m->type,
                'issue_description' => $m->issue_description,
                'status'            => $m->status,
                'cost'              => (float) $m->cost,
                'reported_on'       => $m->reported_on?->format('Y-m-d'),
            ]),
        ]);
    }
}
