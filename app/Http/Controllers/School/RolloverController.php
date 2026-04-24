<?php

namespace App\Http\Controllers\School;

use App\Enums\RolloverState;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseClass;
use App\Models\FeePayment;
use App\Models\RolloverRun;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Services\AcademicRolloverService;
use App\Services\CarryForwardDuesService;
use App\Services\StudentRolloverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RolloverController extends Controller
{
    public function index()
    {
        $school = app('current_school');

        $years = AcademicYear::where('school_id', $school->id)
            ->orderBy('start_date', 'desc')
            ->get();

        $runs = RolloverRun::where('school_id', $school->id)
            ->with(['sourceYear:id,name', 'targetYear:id,name', 'startedBy:id,name'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return Inertia::render('School/Setup/RolloverWizard', [
            'years'       => $years,
            'runs'        => $runs,
            'allModules'  => AcademicRolloverService::ALL_MODULES,
        ]);
    }

    /**
     * Execute the structure-phase rollover. Creates a RolloverRun, runs the
     * service inside a transaction, and returns the run summary.
     */
    public function execute(Request $request, AcademicRolloverService $rolloverService)
    {
        $school = app('current_school');

        $validated = $request->validate([
            'source_year_id' => 'required|exists:academic_years,id',
            'target_year_id' => 'required|exists:academic_years,id|different:source_year_id',
            'modules'        => 'required|array|min:1',
            'modules.*'      => 'string|in:' . implode(',', AcademicRolloverService::ALL_MODULES),
        ]);

        $sourceYear = AcademicYear::where('school_id', $school->id)->findOrFail($validated['source_year_id']);
        $targetYear = AcademicYear::where('school_id', $school->id)->findOrFail($validated['target_year_id']);

        if ($targetYear->isFrozen()) {
            return back()->with('error', 'Target year is frozen and cannot accept new data.');
        }

        $run = RolloverRun::create([
            'school_id'      => $school->id,
            'source_year_id' => $sourceYear->id,
            'target_year_id' => $targetYear->id,
            'state'          => RolloverState::Draft,
            'config'         => ['modules' => $validated['modules']],
            'stats'          => [],
            'started_by'     => $request->user()->id,
            'started_at'     => now(),
        ]);

        try {
            $rolloverService->execute($run, $validated['modules']);
            return back()->with('success', "Structure rollover complete (run #{$run->id}).");
        } catch (\Throwable $e) {
            return back()->with('error', "Rollover failed: {$e->getMessage()} (run #{$run->id})");
        }
    }

    /**
     * Promote all students from run's source year to target year.
     * Accepts a dry_run flag to preview counts without writing.
     */
    public function promoteStudents(Request $request, RolloverRun $run, StudentRolloverService $service)
    {
        $school = app('current_school');
        abort_if($run->school_id !== $school->id, 404);

        $validated = $request->validate([
            'dry_run'           => 'nullable|boolean',
            'strategy'          => 'nullable|in:by_numeric_value,explicit_map',
            'class_map'         => 'nullable|array',
            'class_map.*'       => 'nullable|integer|exists:course_classes,id',
            'section_map'       => 'nullable|array',
            'section_map.*'     => 'nullable|integer|exists:sections,id',
            'promote_detained'  => 'nullable|boolean',
        ]);

        try {
            $result = $service->execute($run, $validated);

            if (!empty($validated['dry_run'])) {
                return response()->json(['dry_run' => true, 'summary' => $result]);
            }

            $msg = "Students: {$result['promoted']} promoted, {$result['graduated']} graduated, {$result['detained']} detained.";
            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', "Student rollover failed: {$e->getMessage()}");
        }
    }

    /**
     * Carry unpaid fee balances from source year into target year.
     */
    public function carryForward(Request $request, RolloverRun $run, CarryForwardDuesService $service)
    {
        $school = app('current_school');
        abort_if($run->school_id !== $school->id, 404);

        $validated = $request->validate([
            'dry_run' => 'nullable|boolean',
        ]);

        try {
            $result = $service->execute($run, $validated);
            if (!empty($validated['dry_run'])) {
                return response()->json(['dry_run' => true, 'summary' => $result]);
            }
            $msg = "Carry-forward: {$result['rows_created']} rows for {$result['students_with_dues']} students (₹{$result['total_amount']}).";
            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', "Carry-forward failed: {$e->getMessage()}");
        }
    }

    /**
     * Finalize a run — optionally freeze the source year to lock further edits.
     */
    public function finalize(Request $request, RolloverRun $run, CarryForwardDuesService $service)
    {
        $school = app('current_school');
        abort_if($run->school_id !== $school->id, 404);

        $validated = $request->validate([
            'freeze_source' => 'nullable|boolean',
        ]);

        try {
            $service->finalize($run, (bool) ($validated['freeze_source'] ?? true));
            return back()->with('success', "Rollover run #{$run->id} finalized.");
        } catch (\Throwable $e) {
            return back()->with('error', "Finalize failed: {$e->getMessage()}");
        }
    }

    public function show(RolloverRun $run)
    {
        $school = app('current_school');
        abort_if($run->school_id !== $school->id, 404);

        $run->load(['sourceYear:id,name', 'targetYear:id,name', 'startedBy:id,name']);
        $items = $run->items()->orderBy('id')->paginate(50);

        return Inertia::render('School/Setup/RolloverRunDetail', [
            'run'   => $run,
            'items' => $items,
        ]);
    }

    // ─────────────── Manual (batch-by-batch) student promotion ───────────────
    //
    // The UI wizard drives these endpoints. The operator walks the source year
    // class-by-class, picks specific students, chooses a target class + section,
    // and each batch is promoted + its dues carried atomically.

    /**
     * Render the manual-promotion page for a run. Requires structure_done or a
     * later state that hasn't moved past students.
     */
    public function manualPromoteIndex(RolloverRun $run)
    {
        $school = app('current_school');
        abort_if($run->school_id !== $school->id, 404);

        $allowed = [
            RolloverState::StructureDone,
            RolloverState::StudentsRunning,
        ];
        if (!in_array($run->state, $allowed, true)) {
            return redirect()->route('settings.rollover')
                ->with('error', 'Manual promotion is only available while the students phase is open.');
        }

        $run->load(['sourceYear:id,name', 'targetYear:id,name']);

        return Inertia::render('School/Setup/RolloverManualPromote', [
            'run' => $run,
        ]);
    }

    /**
     * Classes that have at least one StudentAcademicHistory row in the given
     * academic year. Used to populate source + target class dropdowns.
     */
    public function classesForYear(Request $request, RolloverRun $run)
    {
        $school = app('current_school');
        abort_if($run->school_id !== $school->id, 404);

        $validated = $request->validate([
            'year_id' => 'required|integer|exists:academic_years,id',
            'only_with_students' => 'nullable|boolean',
        ]);

        abort_unless(
            in_array((int) $validated['year_id'], [$run->source_year_id, $run->target_year_id], true),
            422,
            'Year must be the run source or target.'
        );

        $query = CourseClass::where('school_id', $school->id);

        if (!empty($validated['only_with_students'])) {
            $query->whereExists(function ($q) use ($school, $validated) {
                $q->select(DB::raw(1))
                    ->from('student_academic_histories')
                    ->whereColumn('student_academic_histories.class_id', 'course_classes.id')
                    ->where('student_academic_histories.school_id', $school->id)
                    ->where('student_academic_histories.academic_year_id', $validated['year_id']);
            });
        }

        $classes = $query->orderBy('numeric_value')
            ->orderBy('name')
            ->get(['id', 'name', 'numeric_value']);

        return response()->json(['classes' => $classes]);
    }

    /**
     * Sections for a given class. For the source year we restrict to sections
     * that actually have students in that year; for the target year we show
     * every section on the class (since the target might be empty).
     */
    public function sectionsForClass(Request $request, RolloverRun $run)
    {
        $school = app('current_school');
        abort_if($run->school_id !== $school->id, 404);

        $validated = $request->validate([
            'class_id' => 'required|integer|exists:course_classes,id',
            'year_id'  => 'nullable|integer|exists:academic_years,id',
            'only_with_students' => 'nullable|boolean',
        ]);

        $class = CourseClass::where('school_id', $school->id)->findOrFail($validated['class_id']);

        $baseQuery = fn() => Section::where('school_id', $school->id)
            ->where('course_class_id', $class->id);

        if (!empty($validated['only_with_students']) && !empty($validated['year_id'])) {
            // Source side: only sections that actually have students enrolled
            // in that year — drives the "promote from" picker.
            $sections = $baseQuery()
                ->whereExists(function ($q) use ($school, $validated) {
                    $q->select(DB::raw(1))
                        ->from('student_academic_histories')
                        ->whereColumn('student_academic_histories.section_id', 'sections.id')
                        ->where('student_academic_histories.school_id', $school->id)
                        ->where('student_academic_histories.academic_year_id', $validated['year_id']);
                })
                ->orderBy('name')
                ->get(['id', 'name', 'course_class_id']);
        } elseif (!empty($validated['year_id'])) {
            // Target side: only sections linked to that year via
            // section_academic_year pivot. If the pivot is empty (brand-new year),
            // fall back to every section on this class so the operator can seed.
            $sections = $baseQuery()
                ->forYear((int) $validated['year_id'])
                ->orderBy('name')
                ->get(['id', 'name', 'course_class_id']);

            if ($sections->isEmpty()) {
                $sections = $baseQuery()->orderBy('name')->get(['id', 'name', 'course_class_id']);
            }
        } else {
            $sections = $baseQuery()->orderBy('name')->get(['id', 'name', 'course_class_id']);
        }

        return response()->json(['sections' => $sections]);
    }

    /**
     * List students in the source (class, section) who have NOT yet been
     * promoted — i.e. they have no StudentAcademicHistory row in the target
     * year. Includes outstanding source-year fee balance so the operator
     * knows what will carry forward.
     */
    public function eligibleStudents(Request $request, RolloverRun $run)
    {
        $school = app('current_school');
        abort_if($run->school_id !== $school->id, 404);

        $validated = $request->validate([
            'class_id'   => 'required|integer|exists:course_classes,id',
            'section_id' => 'nullable|integer|exists:sections,id',
        ]);

        $sourceRows = StudentAcademicHistory::where('school_id', $school->id)
            ->where('academic_year_id', $run->source_year_id)
            ->where('class_id', $validated['class_id'])
            ->when($validated['section_id'] ?? null, fn($q, $s) => $q->where('section_id', $s))
            ->get(['id', 'student_id', 'class_id', 'section_id', 'roll_no', 'status']);

        if ($sourceRows->isEmpty()) {
            return response()->json(['students' => []]);
        }

        $studentIds = $sourceRows->pluck('student_id')->all();

        // Exclude students already present in the target year (promoted already).
        $alreadyInTarget = StudentAcademicHistory::where('school_id', $school->id)
            ->where('academic_year_id', $run->target_year_id)
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id')
            ->all();

        $eligibleIds = array_values(array_diff($studentIds, $alreadyInTarget));
        if (empty($eligibleIds)) {
            return response()->json(['students' => []]);
        }

        $students = Student::where('school_id', $school->id)
            ->whereIn('id', $eligibleIds)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'admission_no', 'erp_no']);

        // Outstanding balance per student from the source year.
        $balances = FeePayment::where('school_id', $school->id)
            ->where('academic_year_id', $run->source_year_id)
            ->whereIn('student_id', $eligibleIds)
            ->where('balance', '>', 0)
            ->whereIn('status', ['due', 'partial'])
            ->where('is_carry_forward', false)
            ->selectRaw('student_id, SUM(balance) as outstanding')
            ->groupBy('student_id')
            ->pluck('outstanding', 'student_id');

        $rowsByStudent = $sourceRows->keyBy('student_id');

        $payload = $students->map(function ($s) use ($rowsByStudent, $balances) {
            $hist = $rowsByStudent->get($s->id);
            return [
                'id'             => $s->id,
                'name'           => trim("{$s->first_name} {$s->last_name}"),
                'admission_no'   => $s->admission_no,
                'erp_no'         => $s->erp_no,
                'roll_no'        => $hist?->roll_no,
                'source_status'  => $hist?->status,
                'outstanding'    => number_format((float) ($balances[$s->id] ?? 0), 2, '.', ''),
            ];
        })->values();

        return response()->json(['students' => $payload]);
    }

    /**
     * Promote the selected students into the chosen target class+section and
     * carry their source-year dues forward in the same transaction.
     */
    public function promoteManual(
        Request $request,
        RolloverRun $run,
        StudentRolloverService $studentService,
        CarryForwardDuesService $feeService
    ) {
        $school = app('current_school');
        abort_if($run->school_id !== $school->id, 404);

        $validated = $request->validate([
            'student_ids'       => 'required|array|min:1',
            'student_ids.*'     => 'integer|exists:students,id',
            'target_class_id'   => 'required|integer|exists:course_classes,id',
            'target_section_id' => 'required|integer|exists:sections,id',
            'carry_fees'        => 'nullable|boolean',
        ]);

        $allowed = [RolloverState::StructureDone, RolloverState::StudentsRunning];
        if (!in_array($run->state, $allowed, true)) {
            return response()->json([
                'message' => "Run is in state '{$run->state->value}' — manual promotion is closed.",
            ], 422);
        }

        try {
            // Flip to students_running on the first batch so the UI reflects progress.
            if ($run->state === RolloverState::StructureDone) {
                $run->transitionTo(RolloverState::StudentsRunning);
            }

            $promoteResult = $studentService->promoteSelected(
                $run,
                $validated['student_ids'],
                (int) $validated['target_class_id'],
                (int) $validated['target_section_id']
            );

            $feeResult = null;
            if ($validated['carry_fees'] ?? true) {
                // Only carry for students we actually promoted this batch.
                $feeResult = $feeService->forStudents($run, $validated['student_ids']);
            }

            return response()->json([
                'promotion' => $promoteResult,
                'fees'      => $feeResult,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Operator signals "we're done promoting" — transitions the run to
     * students_done so the carry-forward phase can proceed globally (if any
     * student still has dues that weren't caught per-batch).
     */
    public function markStudentsDone(RolloverRun $run)
    {
        $school = app('current_school');
        abort_if($run->school_id !== $school->id, 404);

        $allowed = [RolloverState::StructureDone, RolloverState::StudentsRunning];
        if (!in_array($run->state, $allowed, true)) {
            return back()->with('error', "Run is in state '{$run->state->value}' — cannot mark students done.");
        }

        $run->transitionTo(RolloverState::StudentsDone);
        return redirect()->route('settings.rollover')
            ->with('success', 'Student promotion phase marked complete.');
    }
}
