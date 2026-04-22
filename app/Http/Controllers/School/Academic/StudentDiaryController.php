<?php

namespace App\Http\Controllers\School\Academic;

use App\Http\Controllers\Controller;
use App\Models\DiaryCompletion;
use App\Models\DiaryRead;
use App\Models\StudentDiary;
use App\Models\Student;
use App\Models\CourseClass;
use App\Services\TeacherScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StudentDiaryController extends Controller
{
    /**
     * P4: Calendar heatmap — returns dates with entry counts for a given month/class/section.
     * Used by the diary index to render a month calendar with entry dots.
     */
    public function calendar(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $scope          = app(TeacherScopeService::class)->for(auth()->user());

        $year  = (int) ($request->get('year',  now()->year));
        $month = (int) ($request->get('month', now()->month));

        $query = StudentDiary::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        app(\App\Services\TeacherScopeService::class)->applySubjectScope($query, $scope);
        if ($request->filled('class_id'))   $query->where('class_id',   $request->class_id);
        if ($request->filled('section_id')) $query->where('section_id', $request->section_id);

        $counts = $query->selectRaw('DATE(date) as day, count(*) as cnt')
            ->groupBy('day')
            ->pluck('cnt', 'day');

        return response()->json($counts);
    }

    /**
     * P4: CSV export of diary entries for a date range.
     */
    public function export(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $scope          = app(TeacherScopeService::class)->for(auth()->user());

        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $query = StudentDiary::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereBetween('date', [$request->from, $request->to])
            ->with(['courseClass', 'section', 'subject', 'teacher.user'])
            ->orderBy('date');

        app(\App\Services\TeacherScopeService::class)->applySubjectScope($query, $scope);
        if ($request->filled('class_id'))   $query->where('class_id',   $request->class_id);
        if ($request->filled('section_id')) $query->where('section_id', $request->section_id);

        $diaries = $query->get();

        $filename = 'diary-export-' . $request->from . '-to-' . $request->to . '.csv';

        $callback = function () use ($diaries) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Class', 'Section', 'Subject', 'Teacher', 'Content', 'Attachments']);
            foreach ($diaries as $d) {
                fputcsv($handle, [
                    $d->date->format('d M Y'),
                    $d->courseClass?->name ?? '',
                    $d->section?->name ?? '',
                    $d->subject?->name ?? '',
                    $d->teacher?->user?->name ?? '',
                    $d->content,
                    count($d->attachments ?? []),
                ]);
            }
            fclose($handle);
        };

        return Response::stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * P4: Mark a diary entry as read by the current user (parent/student).
     * Called automatically when the student portal diary page loads.
     */
    public function markRead(StudentDiary $diary)
    {
        if ($diary->school_id !== app('current_school_id')) abort(403);

        DiaryRead::firstOrCreate(
            ['diary_id' => $diary->id, 'user_id' => auth()->id()],
            ['read_at' => now()]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * P4: Toggle homework completion for the current student.
     */
    public function toggleCompletion(StudentDiary $diary)
    {
        if ($diary->school_id !== app('current_school_id')) abort(403);

        $student = auth()->user()->student;
        abort_unless($student, 403, 'Only students can mark homework as completed.');

        $existing = DiaryCompletion::where('diary_id', $diary->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return back()->with('success', 'Homework marked as not done.');
        }

        DiaryCompletion::create([
            'diary_id'     => $diary->id,
            'student_id'   => $student->id,
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Homework marked as completed! ✓');
    }

    public function index(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $scope          = app(TeacherScopeService::class)->for(auth()->user());

        $query = StudentDiary::with(['courseClass', 'section', 'subject', 'teacher.user'])
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId);

        // applySubjectScope handles both section and subject filtering via allowedMap,
        // covering mixed scenarios like "class teacher of 1A + English teacher of 2A".
        app(TeacherScopeService::class)->applySubjectScope($query, $scope);

        if ($request->filled('class_id'))   $query->where('class_id', $request->class_id);
        if ($request->filled('section_id')) $query->where('section_id', $request->section_id);
        if ($request->filled('date'))       $query->whereDate('date', $request->date);

        $diaries = $query->withCount(['reads', 'completions'])->latest('date')->paginate(15)->withQueryString();

        $classQuery = CourseClass::with(['sections' => fn($q) => $q->forCurrentYear()])->where('school_id', $schoolId);
        if ($scope->restricted && $scope->classIds->isNotEmpty()) {
            $classQuery->whereIn('id', $scope->classIds);
        }

        return Inertia::render('School/Academic/Diary/Index', [
            'diaries'             => $diaries,
            'classes'             => $classQuery->orderBy('numeric_value')->orderBy('name')->get(),
            'filters'             => $request->only(['class_id', 'section_id', 'date']),
            'teacher_subject_ids' => $scope->subjectRestricted ? $scope->subjectIds->values() : null,
            'allowed_map'         => $scope->restricted ? $scope->allowedMap : null,
        ]);
    }

    /**
     * Student/Parent: view diary for their own class/section.
     */
    public function studentIndex(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $user           = auth()->user();

        $student = null;
        if ($user->user_type === 'student') {
            $student = \App\Models\Student::where('school_id', $schoolId)->where('user_id', $user->id)->first();
        } elseif ($user->user_type === 'parent') {
            $student = \App\Models\Student::where('school_id', $schoolId)
                ->whereHas('studentParent', fn($q) => $q->where('user_id', $user->id))
                ->first();
        }

        $date = $request->filled('date') ? $request->date : now()->toDateString();

        if (!$student || !$student->currentAcademicHistory) {
            return Inertia::render('School/Academic/Diary/StudentIndex', [
                'diaries' => null,
                'filters' => ['date' => $date],
            ]);
        }

        $history = $student->currentAcademicHistory;

        $diaries = StudentDiary::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $history->class_id)
            ->where('section_id', $history->section_id)
            ->whereDate('date', $date)
            ->with(['subject', 'teacher.user'])
            ->withCount('completions')
            ->latest('date')
            ->paginate(20);

        // Auto-mark all shown entries as read
        $diaryIds = $diaries->pluck('id');
        foreach ($diaryIds as $did) {
            DiaryRead::firstOrCreate(
                ['diary_id' => $did, 'user_id' => auth()->id()],
                ['read_at' => now()]
            );
        }

        // Which entries has this student already completed?
        $myCompletions = [];
        if ($user->user_type === 'student' && $student) {
            $myCompletions = DiaryCompletion::where('student_id', $student->id)
                ->whereIn('diary_id', $diaryIds)
                ->pluck('diary_id')
                ->toArray();
        }

        return Inertia::render('School/Academic/Diary/StudentIndex', [
            'diaries'       => $diaries,
            'myCompletions' => $myCompletions,
            'filters'       => ['date' => $date],
        ]);
    }

    public function create()
    {
        $schoolId = app('current_school_id');
        $scope    = app(TeacherScopeService::class)->for(auth()->user());

        $classQuery = CourseClass::where('school_id', $schoolId)->with(['subjects', 'sections' => fn($q) => $q->forCurrentYear()->with('subjects')]);
        if ($scope->restricted && $scope->classIds->isNotEmpty()) {
            $classQuery->whereIn('id', $scope->classIds);
        }

        return Inertia::render('School/Academic/Diary/Create', [
            'classes'             => $classQuery->orderBy('numeric_value')->orderBy('name')->get(),
            'teacher_subject_ids' => $scope->subjectRestricted ? $scope->subjectIds->values() : null,
            'allowed_map'         => $scope->restricted ? $scope->allowedMap : null,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $teacherId      = auth()->user()->staff?->id;

        if (!$teacherId && !auth()->user()->isSchoolAdmin()) {
            return back()->with('error', 'Only teachers or admins can create diary entries.');
        }

        $validated = $request->validate([
            'class_id'     => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'section_ids'  => 'required|array|min:1',
            'section_ids.*'=> ['exists:sections,id', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'subject_id'   => ['required', Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'date'         => 'required|date',
            // Strip HTML on server side to prevent XSS
            'content'      => 'required|string|max:5000',
            'attachments'  => 'nullable|array',
            'attachments.*'=> 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png,gif',
        ]);

        // Sanitize content — strip all HTML tags
        $safeContent = strip_tags($validated['content']);

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachments[] = $file->store('academic/diaries', 'public');
            }
        }

        $createdDiaries = [];
        foreach ($validated['section_ids'] as $sectionId) {
            $createdDiaries[] = StudentDiary::create([
                'school_id'       => $schoolId,
                'academic_year_id'=> $academicYearId,
                'class_id'        => $validated['class_id'],
                'section_id'      => $sectionId,
                'subject_id'      => $validated['subject_id'],
                'teacher_id'      => $teacherId,
                'date'            => $validated['date'],
                'content'         => $safeContent,
                'attachments'     => $attachments,
            ]);
        }

        // Notify parents (non-fatal)
        $firstDiary = $createdDiaries[0] ?? null;
        if ($firstDiary) {
            try {
                $school = app('current_school');
                if ($school && class_exists(\App\Services\NotificationService::class)) {
                    $notificationService = new \App\Services\NotificationService($school);
                    $notificationService->notifyDiaryEntry($firstDiary);
                }
            } catch (\Throwable $e) {
                Log::error('Diary notification failed', [
                    'diary_id' => $firstDiary->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('school.academic.diary.index')->with('success', 'Diary entry created for ' . count($createdDiaries) . ' section(s).');
    }

    public function destroy(StudentDiary $diary)
    {
        if ($diary->school_id !== app('current_school_id')) abort(403);

        foreach ($diary->attachments ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }

        $diary->delete();

        return back()->with('success', 'Diary entry deleted.');
    }
}
