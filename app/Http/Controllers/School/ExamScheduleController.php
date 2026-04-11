<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\ExamScheduleSubject;
use App\Models\ClassSubject;
use App\Models\CourseClass;
use App\Models\ExamType;
use App\Models\GradingSystem;
use App\Models\Section;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ExamScheduleController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->can('manage_exam_schedules')) abort(403);

        $schedules = ExamSchedule::with([
            'examType', 'courseClass', 'sections',
            'scholasticGradingSystem', 'coScholasticGradingSystem',
            'scheduleSubjects.subject',
            'scheduleSubjects.examAssessment',
            'scheduleSubjects.markConfigs.examAssessmentItem'
        ])
            ->where('school_id', app('current_school_id'))
            ->where('academic_year_id', app('current_academic_year_id'))
            ->latest()
            ->get();

        $examTypes = ExamType::where('school_id', app('current_school_id'))
            ->where('academic_year_id', app('current_academic_year_id'))
            ->orderBy('name')->get(['id', 'name', 'code']);

        $classes = CourseClass::where('school_id', app('current_school_id'))
            ->with('sections:id,course_class_id,name')
            ->orderBy('numeric_value')->get(['id', 'name']);

        $gradingSystems = GradingSystem::where('school_id', app('current_school_id'))
            ->where('academic_year_id', app('current_academic_year_id'))
            ->orderBy('type')->orderBy('name')
            ->get(['id', 'name', 'type']);

        $assessments = \App\Models\ExamAssessment::where('school_id', app('current_school_id'))
            ->where('academic_year_id', app('current_academic_year_id'))
            ->with('items:id,exam_assessment_id,name,code,sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('School/Examinations/Schedules/Index', [
            'schedules'      => $schedules,
            'examTypes'      => $examTypes,
            'classes'        => $classes,
            'gradingSystems' => $gradingSystems,
            'assessments'    => $assessments,
        ]);
    }

    /** AJAX: load subjects for a class (optionally with co-scholastic) */
    public function subjects(Request $request)
    {
        abort_if(!$request->user()->can('manage_exam_schedules') && !$request->user()->isSchoolManagement(), 403);
        $classId = $request->query('class_id');
        $withCoScholastic = $request->boolean('with_co_scholastic', false);

        $query = ClassSubject::with('subject:id,name,code,is_co_scholastic')
            ->where('school_id', app('current_school_id'))
            ->where('course_class_id', $classId)
            ->whereNull('section_id'); // only class-level assignments

        if (!$withCoScholastic) {
            $query->where('is_co_scholastic', false);
        }

        // Deduplicate by subject
        $subjects = $query->get()
            ->unique('subject_id')
            ->map(fn($cs) => [
                'id'              => $cs->subject->id,
                'name'            => $cs->subject->name,
                'code'            => $cs->subject->code,
                'is_co_scholastic' => $cs->subject->is_co_scholastic ?? $cs->is_co_scholastic,
            ])->values();

        return response()->json($subjects);
    }

    public function store(Request $request)
    {
        if (!$request->user()->can('manage_exam_schedules')) abort(403);

        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'exam_type_id'      => ['required', Rule::exists('exam_types', 'id')->where('school_id', $schoolId)],
            'course_class_id'   => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'section_ids'       => 'required|array|min:1',
            'section_ids.*'     => [Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'has_co_scholastic' => 'boolean',
            'scholastic_grading_system_id'    => 'nullable|exists:grading_systems,id',
            'co_scholastic_grading_system_id' => 'nullable|exists:grading_systems,id',
            'subjects'          => 'nullable|array',
            'subjects.*.subject_id'         => 'required|exists:subjects,id',
            'subjects.*.exam_assessment_id' => 'nullable|exists:exam_assessments,id',
            'subjects.*.is_co_scholastic'   => 'boolean',
            'subjects.*.is_enabled'         => 'boolean',
            'subjects.*.exam_date'          => 'nullable|date',
            'subjects.*.exam_time'          => 'nullable|date_format:H:i',
            'subjects.*.duration_minutes'   => 'nullable|integer|min:1',
            'subjects.*.marks'              => 'nullable|array',
            'subjects.*.marks.*.exam_assessment_item_id' => 'required|exists:exam_assessment_items,id',
            'subjects.*.marks.*.max_marks'     => 'required|numeric|min:0',
            'subjects.*.marks.*.passing_marks' => 'required|numeric|min:0',
        ]);

        $schedule = ExamSchedule::create([
            'school_id'        => app('current_school_id'),
            'academic_year_id' => app('current_academic_year_id'),
            'exam_type_id'     => $validated['exam_type_id'],
            'course_class_id'  => $validated['course_class_id'],
            'has_co_scholastic'=> $validated['has_co_scholastic'] ?? false,
            'scholastic_grading_system_id'    => $validated['scholastic_grading_system_id'] ?? null,
            'co_scholastic_grading_system_id' => $validated['co_scholastic_grading_system_id'] ?? null,
            'status'           => 'draft',
        ]);

        $schedule->sections()->sync($validated['section_ids']);

        foreach ($validated['subjects'] ?? [] as $sub) {
            $scheduleSubject = $schedule->scheduleSubjects()->create([
                'subject_id'         => $sub['subject_id'],
                'exam_assessment_id' => $sub['exam_assessment_id'] ?? null,
                'is_co_scholastic'   => $sub['is_co_scholastic'] ?? false,
                'is_enabled'         => $sub['is_enabled'] ?? true,
                'exam_date'          => $sub['exam_date'] ?? null,
                'exam_time'          => $sub['exam_time'] ?? null,
                'duration_minutes'   => $sub['duration_minutes'] ?? null,
            ]);

            if (!empty($sub['marks'])) {
                foreach ($sub['marks'] as $mark) {
                    $scheduleSubject->markConfigs()->create([
                        'exam_assessment_item_id' => $mark['exam_assessment_item_id'],
                        'max_marks'               => $mark['max_marks'],
                        'passing_marks'           => $mark['passing_marks'],
                    ]);
                }
            }
        }

        return redirect('/school/exam-schedules')->with('success', 'Exam Schedule created.');
    }

    public function update(Request $request, ExamSchedule $examSchedule)
    {
        if (!$request->user()->can('manage_exam_schedules')) abort(403);
        abort_if($examSchedule->school_id !== app('current_school_id'), 403);

        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'exam_type_id'      => ['required', Rule::exists('exam_types', 'id')->where('school_id', $schoolId)],
            'course_class_id'   => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'section_ids'       => 'required|array|min:1',
            'section_ids.*'     => [Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'has_co_scholastic' => 'boolean',
            'scholastic_grading_system_id'    => 'nullable|exists:grading_systems,id',
            'co_scholastic_grading_system_id' => 'nullable|exists:grading_systems,id',
            'subjects'          => 'nullable|array',
            'subjects.*.subject_id'         => 'required|exists:subjects,id',
            'subjects.*.exam_assessment_id' => 'nullable|exists:exam_assessments,id',
            'subjects.*.is_co_scholastic'   => 'boolean',
            'subjects.*.is_enabled'         => 'boolean',
            'subjects.*.exam_date'          => 'nullable|date',
            'subjects.*.exam_time'          => 'nullable|date_format:H:i',
            'subjects.*.duration_minutes'   => 'nullable|integer|min:1',
            'subjects.*.marks'              => 'nullable|array',
            'subjects.*.marks.*.exam_assessment_item_id' => 'required|exists:exam_assessment_items,id',
            'subjects.*.marks.*.max_marks'     => 'required|numeric|min:0',
            'subjects.*.marks.*.passing_marks' => 'required|numeric|min:0',
        ]);

        $examSchedule->update([
            'exam_type_id'     => $validated['exam_type_id'],
            'course_class_id'  => $validated['course_class_id'],
            'has_co_scholastic'=> $validated['has_co_scholastic'] ?? false,
            'scholastic_grading_system_id'    => $validated['scholastic_grading_system_id'] ?? null,
            'co_scholastic_grading_system_id' => $validated['co_scholastic_grading_system_id'] ?? null,
        ]);

        $examSchedule->sections()->sync($validated['section_ids']);

        // Safety check: Don't allow re-creating subjects if student marks exist.
        if ($examSchedule->scheduleSubjects()->whereHas('examMarks')->exists()) {
             return back()->withErrors(['subjects' => 'Cannot update subjects because student marks have already been recorded for this exam.']);
        }

        // Recreate subjects & marks
        $examSchedule->scheduleSubjects()->delete();
        foreach ($validated['subjects'] ?? [] as $sub) {
            $scheduleSubject = $examSchedule->scheduleSubjects()->create([
                'subject_id'         => $sub['subject_id'],
                'exam_assessment_id' => $sub['exam_assessment_id'] ?? null,
                'is_co_scholastic'   => $sub['is_co_scholastic'] ?? false,
                'is_enabled'         => $sub['is_enabled'] ?? true,
                'exam_date'          => $sub['exam_date'] ?? null,
                'exam_time'          => $sub['exam_time'] ?? null,
                'duration_minutes'   => $sub['duration_minutes'] ?? null,
            ]);

            if (!empty($sub['marks'])) {
                foreach ($sub['marks'] as $mark) {
                    $scheduleSubject->markConfigs()->create([
                        'exam_assessment_item_id' => $mark['exam_assessment_item_id'],
                        'max_marks'               => $mark['max_marks'],
                        'passing_marks'           => $mark['passing_marks'],
                    ]);
                }
            }
        }

        return redirect('/school/exam-schedules')->with('success', 'Exam Schedule updated.');
    }

    public function destroy(Request $request, ExamSchedule $examSchedule)
    {
        if (!$request->user()->can('manage_exam_schedules')) abort(403);
        abort_if($examSchedule->school_id !== app('current_school_id'), 403);

        if ($examSchedule->scheduleSubjects()->whereHas('examMarks')->exists()) {
            return back()->withErrors(['error' => 'Cannot delete this schedule because student marks have already been recorded.']);
        }

        $examSchedule->scheduleSubjects()->delete();
        $examSchedule->sections()->detach();
        $examSchedule->delete();
        return redirect('/school/exam-schedules')->with('success', 'Exam Schedule deleted.');
    }

    public function togglePublish(Request $request, ExamSchedule $examSchedule)
    {
        if (!$request->user()->can('manage_exam_schedules')) abort(403);
        abort_if($examSchedule->school_id !== app('current_school_id'), 403);

        $newStatus = $examSchedule->status === 'published' ? 'draft' : 'published';
        $examSchedule->update(['status' => $newStatus]);

        if ($newStatus === 'published') {
            (new NotificationService(app('current_school')))->notifyExamPublished($examSchedule);
        }
        
        return redirect('/school/exam-schedules')->with('success', "Exam Schedule status changed to {$newStatus}.");
    }
}
