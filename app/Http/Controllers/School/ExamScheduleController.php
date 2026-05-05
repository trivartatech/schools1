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
use Illuminate\Support\Facades\DB;
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
            ->with(['sections' => fn($q) => $q->forCurrentYear()->select('id','course_class_id','name')])
            ->orderBy('numeric_value')->orderBy('name')->get(['id', 'name']);

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
        $this->normalizeScheduleInput($request);

        $validated = $request->validate([
            'exam_type_id'      => ['required', Rule::exists('exam_types', 'id')->where('school_id', $schoolId)],
            'course_class_id'   => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'section_ids'       => 'required|array|min:1',
            'section_ids.*'     => [Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'weightage'         => 'required|numeric|min:0|max:100',
            'has_co_scholastic' => 'boolean',
            'scholastic_grading_system_id'    => ['nullable', Rule::exists('grading_systems', 'id')->where('school_id', $schoolId)],
            'co_scholastic_grading_system_id' => ['nullable', Rule::exists('grading_systems', 'id')->where('school_id', $schoolId)],
            'subjects'          => 'nullable|array',
            'subjects.*.subject_id'         => 'required|exists:subjects,id',
            'subjects.*.exam_assessment_id' => ['nullable', Rule::exists('exam_assessments', 'id')->where('school_id', $schoolId)],
            'subjects.*.is_co_scholastic'   => 'boolean',
            'subjects.*.is_enabled'         => 'boolean',
            'subjects.*.exam_date'          => 'nullable|date',
            'subjects.*.exam_time'          => 'nullable|date_format:H:i',
            'subjects.*.duration_minutes'   => 'nullable|integer|min:1',
            'subjects.*.marks'              => 'nullable|array',
            'subjects.*.marks.*.exam_assessment_item_id' => ['required', Rule::exists('exam_assessment_items', 'id')->where('school_id', $schoolId)],
            'subjects.*.marks.*.max_marks'     => 'required|numeric|min:0',
            'subjects.*.marks.*.passing_marks' => 'required|numeric|min:0',
        ]);

        $schedule = ExamSchedule::create([
            'school_id'        => app('current_school_id'),
            'academic_year_id' => app('current_academic_year_id'),
            'exam_type_id'     => $validated['exam_type_id'],
            'course_class_id'  => $validated['course_class_id'],
            'weightage'        => $validated['weightage'],
            'has_co_scholastic'=> $validated['has_co_scholastic'] ?? false,
            'scholastic_grading_system_id'    => ($validated['scholastic_grading_system_id']    ?? null) ?: null,
            'co_scholastic_grading_system_id' => ($validated['co_scholastic_grading_system_id'] ?? null) ?: null,
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
        $this->normalizeScheduleInput($request);

        $validated = $request->validate([
            'exam_type_id'      => ['required', Rule::exists('exam_types', 'id')->where('school_id', $schoolId)],
            'course_class_id'   => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'section_ids'       => 'required|array|min:1',
            'section_ids.*'     => [Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'weightage'         => 'required|numeric|min:0|max:100',
            'has_co_scholastic' => 'boolean',
            'scholastic_grading_system_id'    => ['nullable', Rule::exists('grading_systems', 'id')->where('school_id', $schoolId)],
            'co_scholastic_grading_system_id' => ['nullable', Rule::exists('grading_systems', 'id')->where('school_id', $schoolId)],
            'subjects'          => 'nullable|array',
            'subjects.*.subject_id'         => 'required|exists:subjects,id',
            'subjects.*.exam_assessment_id' => ['nullable', Rule::exists('exam_assessments', 'id')->where('school_id', $schoolId)],
            'subjects.*.is_co_scholastic'   => 'boolean',
            'subjects.*.is_enabled'         => 'boolean',
            'subjects.*.exam_date'          => 'nullable|date',
            'subjects.*.exam_time'          => 'nullable|date_format:H:i',
            'subjects.*.duration_minutes'   => 'nullable|integer|min:1',
            'subjects.*.marks'              => 'nullable|array',
            'subjects.*.marks.*.exam_assessment_item_id' => ['required', Rule::exists('exam_assessment_items', 'id')->where('school_id', $schoolId)],
            'subjects.*.marks.*.max_marks'     => 'required|numeric|min:0',
            'subjects.*.marks.*.passing_marks' => 'required|numeric|min:0',
        ]);

        // Header fields (grading scale, weightage, exam type, sections, etc.)
        // can ALWAYS be updated — they don't touch student marks. Only the
        // subjects-level update is conditional on whether marks exist.
        $protectedSubjectIds = $examSchedule->scheduleSubjects()
            ->whereHas('examMarks')
            ->pluck('subject_id')
            ->all();

        DB::transaction(function () use ($examSchedule, $validated, $protectedSubjectIds) {
            // Cast empty strings to null for nullable FKs so MySQL doesn't
            // silently coerce '' → 0 if Laravel's ConvertEmptyStringsToNull
            // middleware is disabled in some environment.
            $scholasticId    = $validated['scholastic_grading_system_id']    ?? null;
            $coScholasticId  = $validated['co_scholastic_grading_system_id'] ?? null;
            if ($scholasticId    === '') $scholasticId    = null;
            if ($coScholasticId  === '') $coScholasticId  = null;

            // 1. Header — always safe to update.
            $examSchedule->update([
                'exam_type_id'     => $validated['exam_type_id'],
                'course_class_id'  => $validated['course_class_id'],
                'weightage'        => $validated['weightage'],
                'has_co_scholastic'=> $validated['has_co_scholastic'] ?? false,
                'scholastic_grading_system_id'    => $scholasticId,
                'co_scholastic_grading_system_id' => $coScholasticId,
            ]);

            $examSchedule->sections()->sync($validated['section_ids']);

            // 2. Subjects — smart upsert by subject_id so we don't blow away
            //    rows that have marks recorded against them.
            $existing  = $examSchedule->scheduleSubjects()->get()->keyBy('subject_id');
            $newBySubj = collect($validated['subjects'] ?? [])->keyBy('subject_id');

            // Delete only old subjects that are no longer in the new list AND
            // have no marks recorded. Subjects with marks stay even if absent
            // from the form (silent — saves what we can).
            foreach ($existing as $subjectId => $oldSub) {
                if (!$newBySubj->has($subjectId) && !in_array($subjectId, $protectedSubjectIds)) {
                    $oldSub->markConfigs()->delete();
                    $oldSub->delete();
                }
            }

            // Upsert from the new list.
            foreach ($newBySubj as $subjectId => $sub) {
                $row = $existing->get($subjectId);
                $isProtected = in_array($subjectId, $protectedSubjectIds);

                $payload = [
                    'is_co_scholastic' => $sub['is_co_scholastic'] ?? false,
                    'is_enabled'       => $sub['is_enabled'] ?? true,
                    'exam_date'        => $sub['exam_date'] ?? null,
                    'exam_time'        => $sub['exam_time'] ?? null,
                    'duration_minutes' => $sub['duration_minutes'] ?? null,
                ];
                // exam_assessment_id + mark configs only change when no marks
                // exist for this subject yet (otherwise the mark schema would
                // be inconsistent with the recorded values).
                if (!$isProtected) {
                    $payload['exam_assessment_id'] = $sub['exam_assessment_id'] ?? null;
                }

                if ($row) {
                    $row->update($payload);
                } else {
                    $row = $examSchedule->scheduleSubjects()->create(array_merge(
                        ['subject_id' => $sub['subject_id']],
                        $payload,
                        // For new rows we always set assessment_id; protection
                        // doesn't apply because there can't be marks yet.
                        ['exam_assessment_id' => $sub['exam_assessment_id'] ?? null]
                    ));
                }

                // Mark configs — replace only if not protected.
                if (!$isProtected) {
                    $row->markConfigs()->delete();
                    foreach ($sub['marks'] ?? [] as $mark) {
                        $row->markConfigs()->create([
                            'exam_assessment_item_id' => $mark['exam_assessment_item_id'],
                            'max_marks'               => $mark['max_marks'],
                            'passing_marks'           => $mark['passing_marks'],
                        ]);
                    }
                }
            }
        });

        $msg = 'Exam Schedule updated.';
        if (!empty($protectedSubjectIds)) {
            $msg .= ' Note: subjects with recorded marks kept their existing assessment + mark configuration.';
        }
        return redirect('/school/exam-schedules')->with('success', $msg);
    }

    public function destroy(Request $request, ExamSchedule $examSchedule)
    {
        if (!$request->user()->can('manage_exam_schedules')) abort(403);
        abort_if($examSchedule->school_id !== app('current_school_id'), 403);

        if ($examSchedule->scheduleSubjects()->whereHas('examMarks')->exists()) {
            return back()->with('error', 'Cannot delete this schedule because student marks have already been recorded.');
        }

        // Delete mark configs (ExamScheduleSubjectMark) before removing subjects
        // — there is no DB-level cascade so we must do it explicitly.
        $examSchedule->scheduleSubjects->each(fn ($ss) => $ss->markConfigs()->delete());
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

    /**
     * Coerce '' → null on nullable fields and drop blank mark rows so the
     * validator doesn't reject the request just because the form sent
     * empty-strings instead of absent values. Mutates the request in place.
     */
    private function normalizeScheduleInput(Request $request): void
    {
        $payload = $request->all();

        foreach (['scholastic_grading_system_id', 'co_scholastic_grading_system_id'] as $k) {
            if (isset($payload[$k]) && $payload[$k] === '') $payload[$k] = null;
        }

        if (is_array($payload['subjects'] ?? null)) {
            foreach ($payload['subjects'] as &$s) {
                foreach (['exam_assessment_id', 'exam_date', 'exam_time', 'duration_minutes'] as $k) {
                    if (isset($s[$k]) && $s[$k] === '') $s[$k] = null;
                }
                // Drop empty mark rows — keep a row only if all three required
                // fields have a non-empty value. This stops a half-filled form
                // line from tripping `required` validation.
                if (is_array($s['marks'] ?? null)) {
                    $s['marks'] = array_values(array_filter($s['marks'], function ($m) {
                        return isset($m['exam_assessment_item_id'], $m['max_marks'], $m['passing_marks'])
                            && $m['exam_assessment_item_id'] !== ''
                            && $m['max_marks'] !== ''
                            && $m['passing_marks'] !== '';
                    }));
                }
            }
            unset($s);
        }

        $request->merge($payload);
    }
}
