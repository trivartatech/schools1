<?php

namespace App\Http\Controllers\School;

use App\Enums\RolloverState;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\RolloverRun;
use App\Services\AcademicRolloverService;
use App\Services\CarryForwardDuesService;
use App\Services\StudentRolloverService;
use Illuminate\Http\Request;
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
}
