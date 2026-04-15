<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreStudentRequest;
use App\Models\Student;
use App\Models\CourseClass;
use App\Models\Section;
use App\Models\StudentAcademicHistory;
use App\Models\FeeStructure;
use App\Models\FeePayment;
use App\Models\EditRequest;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\TransportStudentAllocation;
use App\Models\StudentParent;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\TeacherScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        // ── Teacher data scope ─────────────────────────────────────────────────
        $scope = app(TeacherScopeService::class)->for(auth()->user());

        $query = Student::with(['studentParent', 'currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->where('school_id', $schoolId);

        // ── Portal User Scope (Parents only see their children, Students only see themselves) ─
        if (auth()->user()->isParent()) {
            $parentId = auth()->user()->studentParent?->id;
            $parentId ? $query->where('parent_id', $parentId) : $query->whereRaw('0 = 1');
        } elseif (auth()->user()->isStudent()) {
            $studentId = auth()->user()->student?->id;
            $studentId ? $query->where('id', $studentId) : $query->whereRaw('0 = 1');
        }

        // Filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name',   'like', "%{$search}%")
                  ->orWhere('last_name',  'like', "%{$search}%")
                  ->orWhere('admission_no','like', "%{$search}%")
                  ->orWhere('erp_no',     'like', "%{$search}%")
                  ->orWhere('roll_no',    'like', "%{$search}%")
                  ->orWhereHas('studentParent', fn($p) =>
                      $p->where('primary_phone', 'like', "%{$search}%")
                        ->orWhere('father_name', 'like', "%{$search}%")
                        ->orWhere('mother_name', 'like', "%{$search}%")
                  );
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // ── Apply teacher class/section restriction ─────────────────────────────
        if ($scope->restricted) {
            $query->whereHas('currentAcademicHistory', function ($q) use ($academicYearId, $scope) {
                $q->where('academic_year_id', $academicYearId)
                  ->where('status', 'current')
                  ->whereIn('section_id', $scope->sectionIds);
            });
        }

        if ($request->filled('class_id')) {
            $query->whereHas('currentAcademicHistory', function ($q) use ($request, $academicYearId) {
                $q->where('academic_year_id', $academicYearId)
                  ->where('class_id', $request->class_id);
            });
        }

        if ($request->filled('section_id')) {
            $query->whereHas('currentAcademicHistory', function ($q) use ($request, $academicYearId) {
                $q->where('academic_year_id', $academicYearId)
                  ->where('section_id', $request->section_id);
            });
        }

        // Page size — allowlist prevents abuse, default 20 matches historical behaviour.
        $perPage = (int) $request->input('per_page', 20);
        if (! in_array($perPage, [20, 40, 60, 100], true)) {
            $perPage = 20;
        }

        $students = $query->paginate($perPage)->withQueryString();

        // ----------------------------------------------------
        // Calculate Fee Summary for the paginated students
        // ----------------------------------------------------
        if ($academicYearId && $students->count() > 0) {
            $studentIds = $students->pluck('id');
            $studentClasses = $students->pluck('currentAcademicHistory.class_id')->filter()->unique();
            
            $structures = FeeStructure::where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->whereIn('class_id', $studentClasses)
                ->get();
                
            $payments = FeePayment::where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->whereIn('student_id', $studentIds)
                ->get();

            // Pre-calculate history counts to avoid N+1 inside transform
            $historyCounts = StudentAcademicHistory::where('school_id', $schoolId)
                ->whereIn('student_id', $studentIds)
                ->selectRaw('student_id, count(*) as count')
                ->groupBy('student_id')
                ->pluck('count', 'student_id');

            $students->getCollection()->transform(function ($student) use ($structures, $payments, $historyCounts) {
                $classId = $student->currentAcademicHistory?->class_id;
                
                // Student properties for optional fees
                $hCount = $historyCounts[$student->id] ?? 0;
                $studentType = $hCount > 1 ? 'old' : 'new';
                $gender = strtolower($student->gender ?? 'all');
    
                $studentStructures = $structures->where('class_id', $classId)
                    ->filter(function($s) use ($studentType, $gender) {
                        return in_array($s->student_type, ['all', $studentType]) && 
                               in_array($s->gender, ['all', $gender]);
                    });
    
                $totalDue = $studentStructures->sum('amount');
                
                $studentPayments = $payments->where('student_id', $student->id);
                $totalPaidReal = $studentPayments->sum('amount_paid');
                // Calculate true "paid off" value (including discounts, minus fines)
                // But generally totalDue - totalPaidReal is balance. Let's use simple logic:
                $totalPaid = $totalPaidReal;
                $totalMod = $studentPayments->sum('discount') - $studentPayments->sum('fine');
                
                $student->setAttribute('fee_total', $totalDue);
                $student->setAttribute('fee_paid', $totalPaidReal);
                $student->setAttribute('fee_balance', max(0, $totalDue - $totalPaidReal - $totalMod));
                
                return $student;
            });
        }


        // ── Class list scoped to teacher's assignments ──────────────────────────
        $classQuery = CourseClass::where('school_id', $schoolId);
        if ($scope->restricted && $scope->classIds->isNotEmpty()) {
            $classQuery->whereIn('id', $scope->classIds);
        }
        $classes = $classQuery->get();

        return Inertia::render('School/Students/Index', [
            'students'            => $students,
            'classes'             => $classes,
            'filters'             => array_merge(
                $request->only(['search', 'class_id', 'section_id', 'status']),
                ['per_page' => $perPage],
            ),
            'teacher_section_ids' => $scope->restricted ? $scope->sectionIds->values() : null,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $schoolId = app('current_school_id');
        $classes  = CourseClass::where('school_id', $schoolId)->get();
        $routes   = TransportRoute::where('school_id', $schoolId)
            ->where('status', 'active')
            ->with(['stops' => fn($q) => $q->orderBy('stop_order')])
            ->get();

        return Inertia::render('School/Students/Create', [
            'classes' => $classes,
            'routes'  => $routes,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudentRequest $request, AdmissionService $admissionService)
    {
        $schoolId = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        if (!$academicYearId) {
            return back()->with('error', 'No active academic year found. Cannot admit student.');
        }

        $validated = $request->validated();

        // Wrap admission + transport in a single outer transaction so transport failure rolls back student
        $student = DB::transaction(function () use ($validated, $schoolId, $academicYearId, $admissionService) {
            $student = $admissionService->admitStudent($validated, $schoolId, $academicYearId);

            // Create transport allocation if selected
            if (!empty($validated['transport_route_id']) && !empty($validated['transport_stop_id'])) {
                $stop = TransportStop::find($validated['transport_stop_id']);
                TransportStudentAllocation::create([
                    'school_id'    => $schoolId,
                    'student_id'   => $student->id,
                    'route_id'     => $validated['transport_route_id'],
                    'stop_id'      => $validated['transport_stop_id'],
                    'transport_fee'=> $stop?->fee ?? 0,
                    'pickup_type'  => $validated['transport_pickup_type'] ?? 'both',
                    'start_date'   => now()->format('Y-m-d'),
                    'status'       => 'active',
                ]);
            }

            return $student;
        });


        return redirect()->route('school.students.index')->with('success', 'Student admitted successfully! Admission No: ' . $student->admission_no);
    }

    protected $feeService;

    public function __construct(\App\Services\FeeService $feeService)
    {
        $this->feeService = $feeService;
    }

    public function show(Student $student)
    {
        // Tenant guard — prevent cross-school access via route model binding
        abort_if($student->school_id !== app('current_school_id'), 403);

        // Restrict Portal Users
        if (auth()->user()->isParent()) {
            abort_unless($student->parent_id === auth()->user()->studentParent?->id, 403, 'Unauthorized access.');
        } elseif (auth()->user()->isStudent()) {
            abort_unless($student->id === auth()->user()->student?->id, 403, 'Unauthorized access.');
        }

        $student->load([
            'studentParent',
            'academicHistories.courseClass',
            'academicHistories.section',
            'academicHistories.academicYear',
            'documents',
            'healthRecord',
            'currentAcademicHistory',
            'studentParent.students.currentAcademicHistory.courseClass',
            'studentParent.students.currentAcademicHistory.section',
            'transportAllocation.route.stops',
            'transportAllocation.stop',
            'transportAllocation.vehicle',
        ]);

        $schoolId = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        // Calculate Fee Summary using the central FeeService
        if ($academicYearId) {
            $summary = $this->feeService->getStudentFeeSummary($student, $academicYearId, $schoolId);
            
            $student->setAttribute('fee_total', $summary['total_due']);
            $student->setAttribute('fee_paid', $summary['paid']);
            $student->setAttribute('fee_discount', $summary['discount']);
            $student->setAttribute('fee_fine', $summary['fine']);
            $student->setAttribute('fee_balance', $summary['balance']);
        }

        // ── Attendance Summary (current academic year) ─────────────────────────
        $attendanceSummary = ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'half_day' => 0, 'leave' => 0];
        $monthlyAttendance = [];

        if ($academicYearId) {
            $records = \App\Models\Attendance::where('school_id', $schoolId)
                ->where('student_id', $student->id)
                ->where('academic_year_id', $academicYearId)
                ->orderBy('date')
                ->get(['date', 'status']);

            foreach ($records as $rec) {
                $attendanceSummary['total']++;
                $attendanceSummary[$rec->status] = ($attendanceSummary[$rec->status] ?? 0) + 1;

                $monthKey = \Carbon\Carbon::parse($rec->date)->format('M Y');  // e.g. "Jan 2026"
                if (!isset($monthlyAttendance[$monthKey])) {
                    $monthlyAttendance[$monthKey] = ['present' => 0, 'absent' => 0, 'late' => 0, 'half_day' => 0, 'leave' => 0, 'total' => 0];
                }
                $monthlyAttendance[$monthKey][$rec->status]++;
                $monthlyAttendance[$monthKey]['total']++;
            }
        }
        
        // ── Exam Marks Summary ───────────────────────────────────────────────
        $examMarksData = [];
        if ($academicYearId) {
            $marks = \App\Models\ExamMark::where('student_id', $student->id)
                ->where('academic_year_id', $academicYearId)
                ->with([
                    'examScheduleSubject.examSchedule.examType',
                    'examScheduleSubject.subject',
                    'examScheduleSubject.markConfigs',
                    'examScheduleSubject.gradingSystem.grades',
                    'examScheduleSubject.examSchedule.scholasticGradingSystem.grades',
                    'assessmentItem'
                ])
                ->get();

            // Default fallback grading system (Scholastic)
            $defaultGrades = \App\Models\GradingSystem::with('grades')
                ->where('school_id', $schoolId)
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
                    $ss = $items->first()?->examScheduleSubject;
                    $subjectName = $ss?->subject?->name ?? 'Unknown';
                    
                    $totalObtained = 0;
                    $totalMax = 0;
                    $isAbsent = false;

                    foreach ($items as $item) {
                        if ($item->is_absent) $isAbsent = true;
                        $totalObtained += (float)$item->marks_obtained;
                        
                        // Find matching max marks from schedule subject marks config
                        $config = $ss->markConfigs->firstWhere('exam_assessment_item_id', $item->exam_assessment_item_id);
                        $totalMax += (float)($config->max_marks ?? 0);
                    }

                    $percentage = $totalMax > 0 ? ($totalObtained / $totalMax) * 100 : 0;
                    
                    // Grading hierarchy: Subject Specific -> Schedule Scholastic -> Global Scholastic
                    $grades = $ss->gradingSystem ? $ss->gradingSystem->grades : 
                             ($schedule->scholasticGradingSystem ? $schedule->scholasticGradingSystem->grades : $defaultGrades);
                    
                    $matchedGrade = $grades->sortByDesc('min_percentage')
                        ->first(fn($g) => (float)$percentage >= (float)$g->min_percentage);

                    $subjects[] = [
                        'name' => $subjectName,
                        'obtained' => $totalObtained,
                        'max' => $totalMax,
                        'percentage' => round($percentage, 2),
                        'grade' => $isAbsent ? 'ABS' : ($matchedGrade->name ?? '—'),
                        'is_absent' => $isAbsent
                    ];
                }

                $examMarksData[] = [
                    'id' => $scheduleId,
                    'exam_name' => $examName,
                    'subjects' => $subjects
                ];
            }
        }

        // ── Siblings (other students sharing the same parent) ──────────────────
        $siblings = $student->studentParent
            ? $student->studentParent->students
                ->where('id', '!=', $student->id)
                ->values()
            : collect();

        // ── Classes & Academic Years for Record Edit modal ──────────────────
        $classes       = CourseClass::where('school_id', $schoolId)->orderBy('id')->get(['id','name']);
        $sections      = \App\Models\Section::where('school_id', $schoolId)->orderBy('id')->get(['id','name','course_class_id']);
        $academicYears = \App\Models\AcademicYear::where('school_id', $schoolId)->orderByDesc('id')->get(['id','name']);

        // ── Fee Payments history ───────────────────────────────────────────────
        $feePayments = [];
        if ($academicYearId) {
            $feePayments = \App\Models\FeePayment::where('student_id', $student->id)
                ->where('academic_year_id', $academicYearId)
                ->where('school_id', $schoolId)
                ->with(['feeHead.feeGroup', 'collectedBy:id,name'])
                ->orderByDesc('payment_date')
                ->get()
                ->map(fn($p) => [
                    'id'           => $p->id,
                    'receipt_no'   => $p->receipt_no,
                    'payment_date' => $p->payment_date,
                    'payment_mode' => $p->payment_mode,
                    'amount_paid'  => $p->amount_paid,
                    'amount_due'   => $p->amount_due,
                    'discount'     => $p->discount,
                    'fine'         => $p->fine,
                    'balance'      => $p->balance,
                    'fee_head'     => $p->feeHead?->name,
                    'fee_group'    => $p->feeHead?->feeGroup?->name,
                    'collected_by' => $p->collectedBy?->name,
                ]);
        }

        return Inertia::render('School/Students/Show', [
            'student'             => $student,
            'attendanceSummary'   => $attendanceSummary,
            'monthlyAttendance'   => $monthlyAttendance,
            'examMarks'           => $examMarksData,
            'siblings'            => $siblings,
            'classes'             => $classes,
            'sections'            => $sections,
            'academicYears'       => $academicYears,
            'feePayments'         => $feePayments,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Student $student)
    {
        $schoolId = app('current_school_id');
        abort_if($student->school_id !== $schoolId, 403);

        $classes = CourseClass::where('school_id', $schoolId)->get();
        $student->load(['studentParent', 'currentAcademicHistory']);
        
        return Inertia::render('School/Students/Edit', [
            'student' => $student,
            'classes' => $classes,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Student $student)
    {
        $schoolId = app('current_school_id');
        abort_if($student->school_id !== $schoolId, 403);

        $validated = $request->validate([
            // Identity
            'admission_no' => [
                'required', 'string', 'max:50',
                \Illuminate\Validation\Rule::unique('students', 'admission_no')
                    ->where('school_id', $schoolId)
                    ->ignore($student->id),
            ],
            // Student Personal
            'first_name'               => 'required|string|max:255',
            'last_name'                => 'nullable|string|max:255',
            'dob'                      => 'required|date',
            'birth_place'              => 'nullable|string|max:255',
            'mother_tongue'            => 'nullable|string|max:50',
            'gender'                   => 'required|in:Male,Female,Other',
            'blood_group'              => 'nullable|string|max:10',
            'religion'                 => 'nullable|string|max:50',
            'caste'                    => 'nullable|string|max:50',
            'category'                 => 'nullable|string|max:50',
            'aadhaar_no'               => 'nullable|digits:12',
            'nationality'              => 'nullable|string|max:100',
            'photo'                    => 'nullable|image|max:5120',
            'address'                  => 'nullable|string',
            'city'                     => 'nullable|string|max:100',
            'state'                    => 'nullable|string|max:100',
            'pincode'                  => 'nullable|digits:6',
            'emergency_contact_name'   => 'nullable|string|max:255',
            'emergency_contact_phone'  => 'nullable|string|max:20',

            // Parent/Guardian
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
        ]);

        $updateData = [
            'admission_no'            => $validated['admission_no'],
            'first_name'              => $validated['first_name'],
            'last_name'               => $validated['last_name']               ?? null,
            'dob'                     => $validated['dob'],
            'birth_place'             => $validated['birth_place']             ?? null,
            'mother_tongue'           => $validated['mother_tongue']           ?? null,
            'gender'                  => $validated['gender'],
            'blood_group'             => $validated['blood_group']             ?? null,
            'religion'                => $validated['religion']                ?? null,
            'caste'                   => $validated['caste']                   ?? null,
            'category'                => $validated['category']                ?? null,
            'aadhaar_no'              => $validated['aadhaar_no']              ?? null,
            'nationality'             => $validated['nationality']             ?? null,
            'address'                 => $validated['address']                 ?? null,
            'city'                    => $validated['city']                    ?? null,
            'state'                   => $validated['state']                   ?? null,
            'pincode'                 => $validated['pincode']                 ?? null,
            'emergency_contact_name'  => $validated['emergency_contact_name']  ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
        ];

        if ($request->hasFile('photo')) {
            if ($student->photo) {
                Storage::disk('public')->delete($student->photo);
            }
            $updateData['photo'] = $request->file('photo')->store('students/photos', 'public');
        }

        $student->update($updateData);

        // Re-link to existing parent if another parent with same primary_phone exists (sibling matching)
        $parentData = [
            'primary_phone'        => $validated['primary_phone'],
            'father_name'          => $validated['father_name']          ?? null,
            'mother_name'          => $validated['mother_name']          ?? null,
            'guardian_name'        => $validated['guardian_name']        ?? null,
            'guardian_email'       => $validated['guardian_email']       ?? null,
            'guardian_phone'       => $validated['guardian_phone']       ?? null,
            'father_phone'         => $validated['father_phone']         ?? null,
            'mother_phone'         => $validated['mother_phone']         ?? null,
            'father_occupation'    => $validated['father_occupation']    ?? null,
            'father_qualification' => $validated['father_qualification'] ?? null,
            'mother_occupation'    => $validated['mother_occupation']    ?? null,
            'mother_qualification' => $validated['mother_qualification'] ?? null,
            'address'              => $validated['parent_address']       ?? null,
        ];

        $existingParent = StudentParent::where('school_id', $schoolId)
            ->where('primary_phone', $validated['primary_phone'])
            ->where('id', '!=', $student->parent_id)
            ->first();

        if ($existingParent) {
            // Another parent record with this phone exists — re-link student to that parent
            $oldParent = $student->studentParent;
            $existingParent->update($parentData);
            $student->update(['parent_id' => $existingParent->id]);

            // Clean up orphaned old parent record (no students left)
            if ($oldParent && $oldParent->id !== $existingParent->id && $oldParent->students()->count() === 0) {
                if ($oldParent->user_id) {
                    User::find($oldParent->user_id)?->delete();
                }
                $oldParent->delete();
            }
        } elseif ($student->studentParent) {
            $student->studentParent->update($parentData);
        }

        return redirect()->route('school.students.show', $student->id)->with('success', 'Student details updated successfully.');
    }

    /**
     * PATCH /school/students/{student}/admission-no
     * Update just the admission number for a specific student.
     */
    public function updateAdmissionNo(Request $request, Student $student)
    {
        $schoolId = app('current_school_id');
        abort_if($student->school_id !== $schoolId, 403);

        $request->validate([
            'admission_no' => [
                'required', 'string', 'max:50',
                \Illuminate\Validation\Rule::unique('students', 'admission_no')
                    ->where('school_id', $schoolId)
                    ->ignore($student->id),
            ],
        ]);

        $student->update(['admission_no' => $request->admission_no]);

        return back()->with('success', 'Admission number updated successfully.');
    }

    /**
     * PATCH /school/students/{student}/record
     * Update the current academic history record details.
     */
    public function updateRecord(Request $request, Student $student)
    {
        abort_if($student->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'admission_no'    => 'nullable|string|max:50',
            'academic_year_id'=> 'nullable|exists:academic_years,id',
            'class_id'        => 'nullable|exists:course_classes,id',
            'section_id'      => 'nullable|exists:sections,id',
            'enrollment_type' => 'nullable|string|max:50',
            'student_type'    => 'nullable|string|max:50',
            'status'          => 'nullable|in:current,promoted,detained,graduated',
            'remarks'         => 'nullable|string|max:1000',
        ]);

        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        // Update admission no on Student model if provided
        if (!empty($validated['admission_no'])) {
            $student->update(['admission_no' => $validated['admission_no']]);
        }

        // Update the current academic history record
        if ($academicYearId) {
            $history = \App\Models\StudentAcademicHistory::where('student_id', $student->id)
                ->where('academic_year_id', $academicYearId)
                ->first();

            if ($history) {
                // Only update keys that were actually present in the request (avoids unintentional nulling)
                $patch = [];
                foreach (['class_id','section_id','enrollment_type','student_type','status','remarks'] as $key) {
                    if (array_key_exists($key, $validated)) {
                        $patch[$key] = $validated[$key];
                    }
                }
                if (!empty($patch)) {
                    $history->update($patch);
                }
            }
        }

        return back()->with('success', 'Record details updated successfully.');
    }

    /**
     * GET /school/students/search?q=...
     * JSON search used by Concessions form type-ahead.
     */
    public function search(Request $request)
    {
        $schoolId = app('current_school_id');
        $q        = $request->get('q', '');

        $students = Student::where('school_id', $schoolId)
            ->with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->where(function ($query) use ($q) {
                $query->where('first_name',    'like', "%{$q}%")
                      ->orWhere('last_name',   'like', "%{$q}%")
                      ->orWhere('admission_no','like', "%{$q}%")
                      ->orWhere('roll_no',     'like', "%{$q}%")
                      ->orWhereHas('studentParent', fn($p) =>
                          $p->where('primary_phone', 'like', "%{$q}%")
                            ->orWhere('father_name',  'like', "%{$q}%")
                            ->orWhere('mother_name',  'like', "%{$q}%")
                      );
            })
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'admission_no', 'roll_no'])
            ->map(function ($s) {
                $classSec = '';
                if ($s->currentAcademicHistory) {
                    $c = $s->currentAcademicHistory->courseClass->name ?? '';
                    $sec = $s->currentAcademicHistory->section->name ?? '';
                    if ($c && $sec) {
                        $classSec = $c . ' - ' . $sec;
                    } elseif ($c) {
                        $classSec = $c;
                    }
                }
                
                return [
                    'id'            => $s->id,
                    'first_name'    => $s->first_name,
                    'last_name'     => $s->last_name,
                    'admission_no'  => $s->admission_no,
                    'class_section' => $classSec,
                ];
            });

        return response()->json($students);
    }

    /**
     * Show form for student/parent to request profile edits.
     */
    public function createRequest(Student $student)
    {
        if (auth()->user()->isParent()) {
            abort_unless($student->parent_id === auth()->user()->studentParent?->id, 403, 'Unauthorized access.');
        } elseif (auth()->user()->isStudent()) {
            abort_unless($student->id === auth()->user()->student?->id, 403, 'Unauthorized access.');
        } else {
            abort_unless(auth()->user()->can('request_edit_students') || auth()->user()->can('edit_students'), 403, 'Unauthorized access.');
        }

        $schoolId = app('current_school_id');
        $student->load(['studentParent']);
        
        return Inertia::render('School/Students/RequestEdit', [
            'student' => clone $student,
        ]);
    }

    /**
     * Store the requested profile edits.
     */
    public function storeRequest(Request $request, Student $student)
    {
        if (auth()->user()->isParent()) {
            abort_unless($student->parent_id === auth()->user()->studentParent?->id, 403, 'Unauthorized access.');
        } elseif (auth()->user()->isStudent()) {
            abort_unless($student->id === auth()->user()->student?->id, 403, 'Unauthorized access.');
        } else {
            abort_unless(auth()->user()->can('request_edit_students') || auth()->user()->can('edit_students'), 403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            // Identity
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'dob' => 'nullable|date',
            'birth_place' => 'nullable|string|max:255',
            'mother_tongue' => 'nullable|string|max:50',
            'blood_group' => 'nullable|string|max:10',
            'religion' => 'nullable|string|max:50',
            'caste' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:50',
            'aadhaar_no' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            
            // Parent/Guardian
            'primary_phone' => 'nullable|string|max:20',
            'father_name' => 'nullable|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'guardian_name' => 'nullable|string|max:255',
            'father_phone' => 'nullable|string|max:20',
            'mother_phone' => 'nullable|string|max:20',
            'father_occupation' => 'nullable|string|max:255',
            'mother_occupation' => 'nullable|string|max:255',
            'parent_address' => 'nullable|string',
            
            'reason' => 'nullable|string|max:1000'
        ]);

        $reason = $request->input('reason');
        unset($validated['reason']);

        $requestedChanges = [];
        
        $checkVal = function($key, $oldVal, $newVal) use (&$requestedChanges) {
            if ($newVal === '') $newVal = null;
            if ($newVal !== null && $oldVal != $newVal) {
                $requestedChanges[$key] = $newVal;
            }
        };

        // Student changes
        $checkVal('first_name', $student->first_name, $validated['first_name'] ?? null);
        $checkVal('last_name', $student->last_name, $validated['last_name'] ?? null);
        $checkVal('dob', $student->dob, $validated['dob'] ?? null);
        $checkVal('birth_place', $student->birth_place, $validated['birth_place'] ?? null);
        $checkVal('mother_tongue', $student->mother_tongue, $validated['mother_tongue'] ?? null);
        $checkVal('blood_group', $student->blood_group, $validated['blood_group'] ?? null);
        $checkVal('religion', $student->religion, $validated['religion'] ?? null);
        $checkVal('caste', $student->caste, $validated['caste'] ?? null);
        $checkVal('category', $student->category, $validated['category'] ?? null);
        $checkVal('aadhaar_no', $student->aadhaar_no, $validated['aadhaar_no'] ?? null);
        $checkVal('address', $student->address, $validated['address'] ?? null);

        // Parent changes
        if ($student->studentParent) {
            $checkVal('primary_phone', $student->studentParent->primary_phone, $validated['primary_phone'] ?? null);
            $checkVal('father_name', $student->studentParent->father_name, $validated['father_name'] ?? null);
            $checkVal('mother_name', $student->studentParent->mother_name, $validated['mother_name'] ?? null);
            $checkVal('guardian_name', $student->studentParent->guardian_name, $validated['guardian_name'] ?? null);
            $checkVal('father_phone', $student->studentParent->father_phone, $validated['father_phone'] ?? null);
            $checkVal('mother_phone', $student->studentParent->mother_phone, $validated['mother_phone'] ?? null);
            $checkVal('father_occupation', $student->studentParent->father_occupation, $validated['father_occupation'] ?? null);
            $checkVal('mother_occupation', $student->studentParent->mother_occupation, $validated['mother_occupation'] ?? null);
            $checkVal('parent_address', $student->studentParent->address, $validated['parent_address'] ?? null);
        }

        if (empty($requestedChanges)) {
            return back()->with('error', 'No actual changes were detected. Request not submitted.');
        }

        EditRequest::create([
            'school_id' => app('current_school_id'),
            'user_id' => auth()->id(), // the person submitting it
            'requestable_type' => Student::class,
            'requestable_id' => $student->id,
            'requested_changes' => $requestedChanges,
            'reason' => $reason,
            'status' => 'pending'
        ]);

        return redirect()->route('school.students.show', $student->id)
            ->with('success', 'Profile update request submitted successfully. It is now pending admin approval.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Student $student)
    {
        abort_if($student->school_id !== app('current_school_id'), 403);

        $student->delete();
        return redirect()->route('school.students.index')->with('success', 'Student record deleted successfully.');
    }

    /**
     * Download Bulk QR Codes Excel Sheet
     */
    public function exportQRCodes(Request $request)
    {
        $schoolId = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $classId = $request->get('class_id');
        $sectionId = $request->get('section_id');

        $fileName = 'Student_QRs_' . date('Y_m_d_His') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\StudentQRExport($schoolId, $academicYearId, $classId, $sectionId),
            $fileName
        );
    }

    /**
     * GET /school/students/bulk-photo
     */
    public function bulkPhotoUploadForm()
    {
        return Inertia::render('School/Students/BulkPhotoUpload');
    }

    /**
     * POST /school/students/bulk-photo
     */
    public function processBulkPhotoUpload(Request $request)
    {
        $request->validate([
            'photos' => 'required|array|min:1',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB per image
        ]);

        $schoolId = app('current_school_id');
        $photos = $request->file('photos');
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($photos as $photo) {
            $originalName = $photo->getClientOriginalName();
            // Get admission no (filename without extension, handles .jpg, .png, etc)
            $admissionNo = pathinfo($originalName, PATHINFO_FILENAME);

            $student = Student::where('school_id', $schoolId)
                ->where('admission_no', $admissionNo)
                ->first();

            if ($student) {
                // Delete old photo if exists
                if ($student->photo) {
                    Storage::disk('public')->delete($student->photo);
                }
                
                $path = $photo->store('students/photos', 'public');
                $student->update(['photo' => $path]);
                
                $results['success'][] = [
                    'admission_no' => $admissionNo,
                    'name' => "{$student->first_name} {$student->last_name}",
                    'file' => $originalName
                ];
            } else {
                $results['failed'][] = [
                    'admission_no' => $admissionNo,
                    'file' => $originalName,
                    'reason' => 'Student with this Admission No not found.'
                ];
            }
        }

        return back()->with([
            'success' => count($results['success']) . ' photos updated successfully.',
            'bulk_results' => $results
        ]);
    }

    /**
     * GET /school/students/scanner
     * Renders the QR scanner page used to look up a student profile.
     * Same camera component as the attendance scanner, but on success
     * we redirect to the student profile page instead of marking present.
     */
    public function qrProfileScanner()
    {
        return Inertia::render('School/Students/QRScanner');
    }

    /**
     * POST /school/students/scan-by-uuid
     * Resolves a scanned QR (raw UUID or "/q/<uuid>" URL) to a student id and
     * returns the redirect URL to that student's profile page. Tenant-scoped
     * to the current school so a QR from another school can't leak data.
     */
    public function scanByUuid(Request $request)
    {
        $request->validate(['uuid' => 'required|string|max:512']);

        $raw = trim($request->uuid);
        // Accept either the bare uuid or a "/q/<uuid>" URL
        if (preg_match('~/q/([^/?#]+)~', $raw, $m)) {
            $uuid = $m[1];
        } else {
            $uuid = $raw;
        }

        $schoolId = app('current_school_id');

        $student = Student::where('school_id', $schoolId)
            ->where('uuid', $uuid)
            ->first();

        if (!$student) {
            return response()->json(['error' => 'Student not found or invalid QR.'], 404);
        }

        return response()->json([
            'success'      => true,
            'student'      => [
                'id'           => $student->id,
                'name'         => $student->name,
                'admission_no' => $student->admission_no,
                'photo_url'    => $student->photo_url,
            ],
            'redirect_url' => route('school.students.show', $student->id),
        ]);
    }
}
