<?php

namespace App\Http\Controllers\School\Academic;

use App\Http\Controllers\Controller;
use App\Models\MaterialDownload;
use App\Models\OnlineClass;
use App\Models\LearningMaterial;
use App\Models\CourseClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ResourceController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');

        $classesQ = OnlineClass::with(['courseClass', 'section', 'subject', 'teacher.user'])
            ->where('school_id', $schoolId);

        if ($request->filled('class_id')) $classesQ->where('class_id', $request->class_id);
        if ($request->filled('subject_id')) $classesQ->where('subject_id', $request->subject_id);

        $materialsQ = LearningMaterial::with(['courseClass', 'section', 'subject', 'teacher.user'])
            ->where('school_id', $schoolId);

        if ($request->filled('class_id')) $materialsQ->where('class_id', $request->class_id);
        if ($request->filled('subject_id')) $materialsQ->where('subject_id', $request->subject_id);
        if ($request->filled('type')) $materialsQ->where('type', $request->type);

        // Teachers and admins see all; students/parents see only published materials
        $user = auth()->user();
        if (in_array($user->user_type, ['student', 'parent'])) {
            $materialsQ->where('is_published', true);
        }

        $onlineClasses     = $classesQ->latest()->paginate(20, ['*'], 'classes');
        $learningMaterials = $materialsQ->withCount('downloads')->latest()->paginate(20, ['*'], 'materials');

        $isManagement = !in_array($user->user_type, ['student', 'parent']);

        return Inertia::render('School/Academic/Resources/Index', [
            'onlineClasses'    => $onlineClasses,
            'learningMaterials'=> $learningMaterials,
            'courseClasses'    => CourseClass::where('school_id', $schoolId)
                ->with(['subjects', 'sections.subjects'])
                ->orderBy('numeric_value')
                ->get(),
            'filters'       => $request->only(['class_id', 'subject_id', 'type', 'published']),
            'isManagement'  => $isManagement,
        ]);
    }

    /**
     * Student/Parent: browse published materials and their class's online classes.
     */
    public function studentIndex(Request $request)
    {
        $schoolId       = app('current_school_id');
        $user           = auth()->user();
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $student = null;
        if ($user->user_type === 'student') {
            $student = \App\Models\Student::where('school_id', $schoolId)->where('user_id', $user->id)->first();
        } elseif ($user->user_type === 'parent') {
            $student = \App\Models\Student::where('school_id', $schoolId)
                ->whereHas('studentParent', fn($q) => $q->where('user_id', $user->id))
                ->first();
        }

        $history  = $student?->currentAcademicHistory;
        $classId  = $history?->class_id;
        $secId    = $history?->section_id;

        $materialsQ = LearningMaterial::where('school_id', $schoolId)
            ->where('is_published', true)
            ->with(['subject', 'teacher.user'])
            ->withCount('downloads');

        if ($classId)  $materialsQ->where('class_id', $classId);
        if ($secId)    $materialsQ->where('section_id', $secId);
        if ($request->filled('subject_id')) $materialsQ->where('subject_id', $request->subject_id);
        if ($request->filled('type'))       $materialsQ->where('type', $request->type);

        $classesQ = OnlineClass::where('school_id', $schoolId)
            ->with(['subject', 'teacher.user']);
        if ($classId) $classesQ->where('class_id', $classId);
        if ($secId)   $classesQ->where('section_id', $secId);

        // Subjects for this class (for filter dropdown)
        $subjects = [];
        if ($classId) {
            $subjects = \App\Models\Subject::whereHas('sections', fn($q) => $q->where('id', $secId))
                ->orWhereHas('courseClasses', fn($q) => $q->where('id', $classId))
                ->where('school_id', $schoolId)
                ->get(['id', 'name']);
        }

        return Inertia::render('School/Academic/Resources/StudentIndex', [
            'materials'    => $materialsQ->latest()->paginate(18),
            'onlineClasses'=> $classesQ->latest('start_time')->paginate(20),
            'subjects'     => $subjects,
            'filters'      => $request->only(['subject_id', 'type']),
        ]);
    }

    public function storeOnlineClass(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'class_id'    => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'section_ids' => 'required|array|min:1',
            'section_ids.*'=> ['exists:sections,id', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'subject_id'  => ['required', Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'start_time'  => 'required|date|after:now',
            'end_time'    => 'nullable|date|after:start_time',
            'meeting_link'=> 'required|url|max:500',
            'platform'    => 'nullable|string|max:50',
            'recording_link' => 'nullable|url|max:500',
        ]);

        $teacherId = auth()->user()->staff?->id;

        foreach ($validated['section_ids'] as $sectionId) {
            OnlineClass::create([
                'school_id'      => $schoolId,
                'class_id'       => $validated['class_id'],
                'section_id'     => $sectionId,
                'subject_id'     => $validated['subject_id'],
                'teacher_id'     => $teacherId,
                'start_time'     => $validated['start_time'],
                'end_time'       => $validated['end_time'] ?? null,
                'meeting_link'   => $validated['meeting_link'],
                'platform'       => $validated['platform'] ?? 'Google Meet',
                'recording_link' => $validated['recording_link'] ?? null,
            ]);
        }

        return back()->with('success', 'Online class scheduled for ' . count($validated['section_ids']) . ' section(s).');
    }

    public function storeMaterial(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'class_id'     => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'section_ids'  => 'required|array|min:1',
            'section_ids.*'=> ['exists:sections,id', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'subject_id'   => ['required', Rule::exists('subjects', 'id')->where('school_id', $schoolId)],
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string|max:1000',
            'type'         => 'required|in:pdf,ppt,video,image,doc,link',
            // Either a file upload OR an external URL — one is required
            'file'         => 'nullable|file|max:20480|mimes:pdf,ppt,pptx,doc,docx,mp4,mov,avi,jpg,jpeg,png,gif',
            'external_url' => 'nullable|url|max:500',
            'chapter_name' => 'nullable|string|max:255',
            'is_published' => 'nullable|boolean',
        ]);

        if (empty($request->file('file')) && empty($validated['external_url'])) {
            return back()->withErrors(['file' => 'Please upload a file or provide an external URL.']);
        }

        $teacherId = auth()->user()->staff?->id;
        $path      = null;

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('academic/materials', 'public');
        }

        foreach ($validated['section_ids'] as $sectionId) {
            LearningMaterial::create([
                'school_id'    => $schoolId,
                'class_id'     => $validated['class_id'],
                'section_id'   => $sectionId,
                'subject_id'   => $validated['subject_id'],
                'teacher_id'   => $teacherId,
                'title'        => $validated['title'],
                'description'  => $validated['description'] ?? null,
                'type'         => $validated['type'],
                'file_path'    => $path,
                'external_url' => $validated['external_url'] ?? null,
                'chapter_name' => $validated['chapter_name'] ?? null,
                'is_published' => $validated['is_published'] ?? true,
            ]);
        }

        return back()->with('success', 'Learning material added to ' . count($validated['section_ids']) . ' section(s).');
    }

    /**
     * Toggle publish/draft status of a material.
     */
    public function togglePublish(LearningMaterial $material)
    {
        if ($material->school_id !== app('current_school_id')) abort(403);

        $material->update(['is_published' => !$material->is_published]);

        $status = $material->is_published ? 'published' : 'set to draft';
        return back()->with('success', "Material {$status}.");
    }

    /**
     * Track that a student downloaded/accessed a material.
     * Supports ?view=1 to return inline (for previewing) instead of downloading.
     */
    public function trackDownload(Request $request, LearningMaterial $material)
    {
        if ($material->school_id !== app('current_school_id')) abort(403);

        $student = auth()->user()->student;

        if ($student) {
            MaterialDownload::firstOrCreate(
                ['learning_material_id' => $material->id, 'student_id' => $student->id],
                ['downloaded_at' => now()]
            );
        }

        // Redirect to the file or external URL
        if ($material->external_url) {
            return redirect()->away($material->external_url);
        }

        if ($material->file_path && Storage::disk('public')->exists($material->file_path)) {
            $disposition = $request->query('view') ? 'inline' : 'attachment';
            return Storage::disk('public')->response(
                $material->file_path,
                basename($material->file_path),
                [],
                $disposition
            );
        }

        return back()->with('error', 'File not found.');
    }

    public function destroyMaterial(LearningMaterial $material)
    {
        if ($material->school_id !== app('current_school_id')) abort(403);

        $filePath = $material->file_path;
        $material->delete();

        // Only delete physical file if no other (non-deleted) records reference it
        $stillReferenced = LearningMaterial::where('file_path', $filePath)->exists();
        if (!$stillReferenced) {
            Storage::disk('public')->delete($filePath);
        }

        return back()->with('success', 'Material deleted.');
    }

    public function destroyOnlineClass(OnlineClass $onlineClass)
    {
        if ($onlineClass->school_id !== app('current_school_id')) abort(403);
        $onlineClass->delete();
        return back()->with('success', 'Online class cancelled.');
    }

    /**
     * Add/update recording link after a class ends.
     */
    public function addRecording(Request $request, OnlineClass $onlineClass)
    {
        if ($onlineClass->school_id !== app('current_school_id')) abort(403);

        $validated = $request->validate([
            'recording_link' => 'required|url|max:500',
        ]);

        $onlineClass->update($validated);

        return back()->with('success', 'Recording link saved.');
    }
}
