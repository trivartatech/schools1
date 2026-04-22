<?php

namespace App\Http\Controllers\School\Academic;

use App\Http\Controllers\Controller;
use App\Models\SyllabusTopic;
use App\Models\SyllabusStatus;
use App\Models\CourseClass;
use App\Models\Section;
use App\Services\TeacherScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SyllabusController extends Controller
{
    public function create()
    {
        $schoolId = app('current_school_id');
        $scope    = app(TeacherScopeService::class)->for(auth()->user());

        $classQuery = CourseClass::where('school_id', $schoolId)->with(['subjects', 'sections' => fn($q) => $q->forCurrentYear()->with('subjects')]);
        if ($scope->restricted && $scope->classIds->isNotEmpty()) {
            $classQuery->whereIn('id', $scope->classIds);
        }

        return Inertia::render('School/Academic/Syllabus/Create', [
            'classes' => $classQuery->orderBy('numeric_value')->orderBy('name')->get(),
        ]);
    }

    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $scope    = app(TeacherScopeService::class)->for(auth()->user());

        $query = SyllabusTopic::where('school_id', $schoolId)
            ->with(['courseClass', 'subject']);

        // applyClassSubjectScope handles class + subject filtering via allowedMap,
        // covering mixed scenarios like "class teacher of 1A + English teacher of 2A":
        // shows ALL topics for Class 1, only English topics for Class 2.
        app(TeacherScopeService::class)->applyClassSubjectScope($query, $scope);

        if ($request->filled('class_id'))   $query->where('class_id', $request->class_id);
        if ($request->filled('subject_id')) $query->where('subject_id', $request->subject_id);

        $topics = $query->orderBy('sort_order')->get();

        // Per-section completion stats for progress bar
        $statuses = collect();
        if ($request->filled('section_id')) {
            $statuses = SyllabusStatus::whereIn('topic_id', $topics->pluck('id'))
                ->where('section_id', $request->section_id)
                ->get()
                ->keyBy('topic_id');
        }

        // Compute progress percentage when a section is selected
        $progressPct = null;
        if ($request->filled('section_id') && $topics->isNotEmpty()) {
            $completed   = $statuses->filter(fn ($s) => $s->status === 'completed')->count();
            $progressPct = round($completed / $topics->count() * 100);
        }

        $classQuery = CourseClass::where('school_id', $schoolId)->with(['subjects', 'sections' => fn($q) => $q->forCurrentYear()->with('subjects')]);
        if ($scope->restricted && $scope->classIds->isNotEmpty()) {
            $classQuery->whereIn('id', $scope->classIds);
        }

        return Inertia::render('School/Academic/Syllabus/Index', [
            'topics'              => $topics,
            'statuses'            => $statuses,
            'progressPct'         => $progressPct,
            'classes'             => $classQuery->orderBy('numeric_value')->orderBy('name')->get(),
            'sections'            => $request->class_id
                ? Section::where('school_id', $schoolId)->where('course_class_id', $request->class_id)->forCurrentYear()->get()
                : [],
            'filters'             => $request->only(['class_id', 'subject_id', 'section_id']),
            'teacher_subject_ids' => $scope->subjectRestricted ? $scope->subjectIds->values() : null,
            'allowed_map'         => $scope->restricted ? $scope->allowedMap : null,
        ]);
    }

    public function storeTopic(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'class_id'    => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'subject_id'  => ['required', Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'chapter_name'=> 'required|string|max:255',
            'topic_name'  => 'required|string|max:255',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        SyllabusTopic::create(array_merge($validated, ['school_id' => $schoolId]));

        return back()->with('success', 'Topic added to syllabus.');
    }

    public function updateTopic(Request $request, SyllabusTopic $topic)
    {
        if ($topic->school_id !== app('current_school_id')) abort(403);

        $validated = $request->validate([
            'chapter_name'=> 'required|string|max:255',
            'topic_name'  => 'required|string|max:255',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $topic->update($validated);

        return back()->with('success', 'Topic updated.');
    }

    public function destroyTopic(SyllabusTopic $topic)
    {
        if ($topic->school_id !== app('current_school_id')) abort(403);
        $topic->delete();
        return back()->with('success', 'Topic deleted.');
    }

    /**
     * Reset all section progress for a given class+subject back to "pending".
     * Used at the start of a new academic year so teachers start fresh.
     */
    public function resetProgress(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'class_id'   => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'subject_id' => ['required', Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'section_id' => ['nullable', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
        ]);

        $topicIds = SyllabusTopic::where('school_id', $schoolId)
            ->where('class_id', $validated['class_id'])
            ->where('subject_id', $validated['subject_id'])
            ->pluck('id');

        if ($topicIds->isEmpty()) {
            return back()->with('error', 'No topics found for this class/subject combination.');
        }

        $query = SyllabusStatus::whereIn('topic_id', $topicIds);
        if (!empty($validated['section_id'])) {
            $query->where('section_id', $validated['section_id']);
        }

        $updated = $query->update([
            'status'         => 'pending',
            'planned_date'   => null,
            'completed_date' => null,
        ]);

        if ($updated === 0) {
            return back()->with('error', 'No section progress records found to reset.');
        }

        return back()->with('success', "Reset {$updated} topic status(es) to Pending. Ready for the new year.");
    }

    /**
     * Export syllabus as CSV for a given class/subject.
     */
    public function export(Request $request)
    {
        $schoolId = app('current_school_id');

        $query = SyllabusTopic::where('school_id', $schoolId)
            ->with(['courseClass', 'subject']);

        if ($request->filled('class_id'))   $query->where('class_id', $request->class_id);
        if ($request->filled('subject_id')) $query->where('subject_id', $request->subject_id);

        $topics = $query->orderBy('sort_order')->get();

        $statuses = collect();
        if ($request->filled('section_id')) {
            $statuses = SyllabusStatus::whereIn('topic_id', $topics->pluck('id'))
                ->where('section_id', $request->section_id)
                ->get()
                ->keyBy('topic_id');
        }

        $rows   = [];
        $rows[] = ['Sort', 'Chapter', 'Topic', 'Status', 'Planned Date', 'Completed Date'];
        foreach ($topics as $topic) {
            $status = $statuses->get($topic->id);
            $rows[] = [
                $topic->sort_order ?? '',
                $topic->chapter_name,
                $topic->topic_name,
                $status ? $status->status : 'pending',
                $status?->planned_date   ?? '',
                $status?->completed_date ?? '',
            ];
        }

        $className   = $topics->first()?->courseClass?->name ?? 'syllabus';
        $subjectName = $topics->first()?->subject?->name ?? '';
        $filename    = "syllabus-{$className}-{$subjectName}-" . now()->format('Y-m-d') . '.csv';

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return Response::stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function updateStatus(Request $request, SyllabusTopic $topic)
    {
        if ($topic->school_id !== app('current_school_id')) abort(403);

        $validated = $request->validate([
            'section_id'     => ['required', Rule::exists('sections', 'id')->where('school_id', $topic->school_id)],
            'status'         => 'required|in:pending,in_progress,completed',
            'planned_date'   => 'nullable|date',
            'completed_date' => 'nullable|date',
        ]);

        SyllabusStatus::updateOrCreate(
            ['topic_id' => $topic->id, 'section_id' => $validated['section_id']],
            [
                'teacher_id'     => auth()->user()->staff?->id,
                'status'         => $validated['status'],
                'planned_date'   => $validated['planned_date'] ?? null,
                'completed_date' => $validated['status'] === 'completed'
                    ? ($validated['completed_date'] ?? now()->toDateString())
                    : null,
            ]
        );

        return back()->with('success', 'Status updated.');
    }
}
