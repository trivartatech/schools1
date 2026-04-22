<?php

namespace App\Http\Controllers\School\Academic;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CourseClass;
use App\Models\Student;
use App\Services\TeacherScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AssignmentController extends Controller
{
    public function create()
    {
        $schoolId = app('current_school_id');
        $scope    = app(\App\Services\TeacherScopeService::class)->for(auth()->user());

        $classQuery = CourseClass::where('school_id', $schoolId)->with(['subjects', 'sections' => fn($q) => $q->forCurrentYear()->with('subjects')]);
        if ($scope->restricted && $scope->classIds->isNotEmpty()) {
            $classQuery->whereIn('id', $scope->classIds);
        }

        return Inertia::render('School/Academic/Assignments/Create', [
            'classes' => $classQuery->orderBy('numeric_value')->orderBy('name')->get(),
        ]);
    }

    public function index(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $scope          = app(TeacherScopeService::class)->for(auth()->user());

        $query = Assignment::with(['courseClass', 'section', 'subject', 'teacher.user'])
            ->withCount('submissions')
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId);

        // applySubjectScope handles both section and subject filtering via allowedMap,
        // covering mixed scenarios like "class teacher of 1A + English teacher of 2A".
        app(TeacherScopeService::class)->applySubjectScope($query, $scope);

        if ($request->filled('class_id'))   $query->where('class_id', $request->class_id);
        if ($request->filled('subject_id')) $query->where('subject_id', $request->subject_id);
        if ($request->filled('status'))     $query->where('status', $request->status);

        $assignments = $query->latest()->paginate(15)->withQueryString();

        $classQuery = CourseClass::where('school_id', $schoolId)->with(['subjects', 'sections' => fn($q) => $q->forCurrentYear()->with('subjects')]);
        if ($scope->restricted && $scope->classIds->isNotEmpty()) {
            $classQuery->whereIn('id', $scope->classIds);
        }

        return Inertia::render('School/Academic/Assignments/Index', [
            'assignments'         => $assignments,
            'classes'             => $classQuery->orderBy('numeric_value')->orderBy('name')->get(),
            'filters'             => $request->only(['class_id', 'subject_id', 'status']),
            'teacher_subject_ids' => $scope->subjectRestricted ? $scope->subjectIds->values() : null,
            'allowed_map'         => $scope->restricted ? $scope->allowedMap : null,
        ]);
    }

    /**
     * Student/Parent: view their own class assignments.
     */
    public function studentIndex(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $user           = auth()->user();

        // Resolve the student
        $student = null;
        if ($user->user_type === 'student') {
            $student = Student::where('school_id', $schoolId)->where('user_id', $user->id)->first();
        } elseif ($user->user_type === 'parent') {
            // Parent: show first child (can be extended for multi-child selection)
            $student = Student::where('school_id', $schoolId)
                ->whereHas('studentParent', fn($q) => $q->where('user_id', $user->id))
                ->first();
        }

        if (!$student) {
            return Inertia::render('School/Academic/Assignments/StudentIndex', [
                'assignments'   => null,
                'mySubmissions' => [],
                'filters'       => [],
            ]);
        }

        // Get student's current class/section
        $history = $student->currentAcademicHistory;

        $assignments = Assignment::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $history?->class_id)
            ->where('section_id', $history?->section_id)
            ->where('status', '!=', 'draft')
            ->with(['subject', 'courseClass'])
            ->latest('due_date')
            ->paginate(20);

        // Student's own submissions keyed by assignment_id
        $mySubmissions = AssignmentSubmission::where('student_id', $student->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->get()
            ->keyBy('assignment_id');

        return Inertia::render('School/Academic/Assignments/StudentIndex', [
            'assignments'   => $assignments,
            'mySubmissions' => $mySubmissions,
            'filters'       => [],
        ]);
    }

    public function store(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $teacherId      = auth()->user()->staff?->id;

        $validated = $request->validate([
            'class_id'     => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'section_ids'  => 'required|array|min:1',
            'section_ids.*'=> ['exists:sections,id', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'subject_id'   => ['required', Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'due_date'     => 'required|date|after_or_equal:today',
            'max_marks'    => 'required|integer|min:1|max:9999',
            'status'       => 'nullable|in:draft,published,closed',
            'attachments'  => 'nullable|array',
            'attachments.*'=> 'nullable|file|max:10240|mimes:pdf,ppt,pptx,doc,docx,jpg,jpeg,png,zip',
        ]);

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachments[] = $file->store('academic/assignments', 'public');
            }
        }

        foreach ($validated['section_ids'] as $sectionId) {
            Assignment::create([
                'school_id'       => $schoolId,
                'academic_year_id'=> $academicYearId,
                'class_id'        => $validated['class_id'],
                'section_id'      => $sectionId,
                'subject_id'      => $validated['subject_id'],
                'teacher_id'      => $teacherId,
                'title'           => $validated['title'],
                'description'     => $validated['description'] ?? null,
                'due_date'        => $validated['due_date'],
                'max_marks'       => $validated['max_marks'],
                'status'          => $validated['status'] ?? 'published',
                'attachments'     => $attachments,
            ]);
        }

        return back()->with('success', 'Assignment created successfully.');
    }

    public function show(Assignment $assignment)
    {
        if ($assignment->school_id !== app('current_school_id')) abort(403);

        $assignment->load(['courseClass', 'section', 'subject', 'teacher.user']);

        $students = Student::where('school_id', $assignment->school_id)
            ->whereHas('academicHistories', function ($q) use ($assignment) {
                $q->where('class_id', $assignment->class_id)
                  ->where('section_id', $assignment->section_id)
                  ->where('academic_year_id', $assignment->academic_year_id);
            })
            ->select('id', 'admission_no', 'first_name', 'last_name')
            ->orderBy('first_name')
            ->get();

        $submissions = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->get()
            ->keyBy('student_id');

        $submissionData = $students->map(function ($student) use ($submissions, $assignment) {
            $submission = $submissions->get($student->id);
            return [
                'id'           => $submission?->id ?? 'pending_' . $student->id,
                'student'      => $student,
                'submitted_at' => $submission?->submitted_at,
                'marks'        => $submission?->marks,
                'remarks'      => $submission?->remarks,
                'is_submitted' => (bool) $submission,
                'attachments'  => $submission?->attachments ?? [],
                'content'      => $submission?->content,
            ];
        });

        // Summary stats
        $submitted = $submissionData->filter(fn ($s) => $s['is_submitted'])->count();
        $graded    = $submissionData->filter(fn ($s) => $s['marks'] !== null)->count();

        return Inertia::render('School/Academic/Assignments/Show', [
            'assignment'  => $assignment,
            'submissions' => $submissionData,
            'stats'       => [
                'total'     => $students->count(),
                'submitted' => $submitted,
                'graded'    => $graded,
                'pending'   => $students->count() - $submitted,
            ],
        ]);
    }

    public function submit(Request $request, Assignment $assignment)
    {
        if ($assignment->school_id !== app('current_school_id')) abort(403);

        $student = auth()->user()->student;
        if (!$student) return back()->with('error', 'Only students can submit assignments.');

        if ($assignment->status !== 'published') {
            return back()->with('error', 'This assignment is not open for submissions.');
        }

        $validated = $request->validate([
            'content'      => 'nullable|string',
            'attachments'  => 'nullable|array',
            'attachments.*'=> 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png,zip',
        ]);

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachments[] = $file->store('academic/submissions', 'public');
            }
        }

        // Flag as late if submitted after the due date
        $isLate = now()->gt($assignment->due_date);

        AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $student->id],
            [
                'submitted_at' => now(),
                'is_late'      => $isLate,
                'content'      => $validated['content'] ?? null,
                'attachments'  => $attachments,
            ]
        );

        $message = $isLate
            ? 'Assignment submitted (late — after the due date).'
            : 'Assignment submitted successfully.';

        return back()->with('success', $message);
    }

    public function gradeStudent(Request $request, Assignment $assignment, Student $student)
    {
        if ($assignment->school_id !== app('current_school_id')) abort(403);

        $validated = $request->validate([
            'marks'   => 'required|numeric|min:0|max:' . $assignment->max_marks,
            'remarks' => 'nullable|string|max:500',
        ]);

        AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $student->id],
            array_merge($validated, ['submitted_at' => now()])
        );

        return back()->with('success', 'Graded successfully.');
    }

    /**
     * Bulk-grade multiple students in a single POST.
     * Payload: grades = [{ student_id, marks, remarks }, ...]
     */
    public function bulkGrade(Request $request, Assignment $assignment)
    {
        if ($assignment->school_id !== app('current_school_id')) abort(403);

        $request->validate([
            'grades'           => 'required|array|min:1',
            'grades.*.student_id' => 'required|exists:students,id',
            'grades.*.marks'      => 'required|numeric|min:0|max:' . $assignment->max_marks,
            'grades.*.remarks'    => 'nullable|string|max:500',
        ]);

        foreach ($request->grades as $grade) {
            AssignmentSubmission::updateOrCreate(
                ['assignment_id' => $assignment->id, 'student_id' => $grade['student_id']],
                [
                    'marks'        => $grade['marks'],
                    'remarks'      => $grade['remarks'] ?? null,
                    'submitted_at' => now(),
                ]
            );
        }

        return back()->with('success', count($request->grades) . ' student(s) graded successfully.');
    }

    /**
     * Duplicate an assignment — creates a copy in draft status.
     */
    public function duplicate(Assignment $assignment)
    {
        if ($assignment->school_id !== app('current_school_id')) abort(403);

        Assignment::create([
            'school_id'        => $assignment->school_id,
            'academic_year_id' => $assignment->academic_year_id,
            'class_id'         => $assignment->class_id,
            'section_id'       => $assignment->section_id,
            'subject_id'       => $assignment->subject_id,
            'teacher_id'       => $assignment->teacher_id,
            'title'            => $assignment->title . ' (Copy)',
            'description'      => $assignment->description,
            'due_date'         => now()->addDays(7)->toDateString(), // default 1 week from now
            'max_marks'        => $assignment->max_marks,
            'status'           => 'draft',
            'attachments'      => $assignment->attachments ?? [],
        ]);

        return back()->with('success', 'Assignment duplicated as draft. Edit it before publishing.');
    }

    public function close(Assignment $assignment)
    {
        if ($assignment->school_id !== app('current_school_id')) abort(403);
        if ($assignment->status !== 'published') return back()->with('error', 'Only published assignments can be closed.');

        $assignment->update(['status' => 'closed']);
        return back()->with('success', 'Assignment has been closed. No further submissions accepted.');
    }

    public function destroy(Assignment $assignment)
    {
        if ($assignment->school_id !== app('current_school_id')) abort(403);

        // Delete all teacher attachment files
        foreach ($assignment->attachments ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }

        // Delete submission attachments
        foreach ($assignment->submissions as $sub) {
            foreach ($sub->attachments ?? [] as $path) {
                Storage::disk('public')->delete($path);
            }
        }

        $assignment->delete();

        return back()->with('success', 'Assignment deleted.');
    }
}
