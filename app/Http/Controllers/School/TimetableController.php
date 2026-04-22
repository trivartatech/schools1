<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TimetableController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->can('view_schedule') && !$user->isSchoolManagement(), 403, 'You do not have permission to view timetables.');

        $schoolId = app('current_school_id');

        $classes = \App\Models\CourseClass::where('school_id', $schoolId)
            ->with(['sections' => function($q) { $q->forCurrentYear()->orderBy('sort_order'); }])
            ->orderBy('sort_order')->get();

        $periods = \App\Models\Period::where('school_id', $schoolId)
            ->orderBy('order')->get();

        $sectionId = $request->section_id;
        $timetables = [];
        $classSubjects = [];
        $section = null;

        if ($sectionId) {
            $section = \App\Models\Section::where('school_id', $schoolId)->find($sectionId);
            abort_if(!$section, 404, 'Section not found.');

            $timetables = \App\Models\Timetable::where('school_id', $schoolId)
                ->where('section_id', $sectionId)
                ->with(['subject', 'staff', 'staff.user'])
                ->get();

            $classSubjects = \App\Models\ClassSubject::with(['subject', 'inchargeStaff', 'inchargeStaff.user'])
                ->where('school_id', $schoolId)
                ->where('course_class_id', $section->course_class_id)
                ->where(function($q) use ($sectionId) {
                    $q->where('section_id', $sectionId)->orWhereNull('section_id');
                })
                ->get();
        }

        return Inertia::render('School/Schedule/Timetable', [
            'school' => app('current_school'),
            'classes' => $classes,
            'periods' => $periods,
            'timetables' => $timetables,
            'classSubjects' => $classSubjects,
            'selectedSectionId' => $sectionId,
            'filters' => $request->only('section_id'),
        ]);
    }

    public function save(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->can('edit_schedule') && !$user->isSchoolManagement(), 403, 'You do not have permission to manage timetables.');

        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'section_id'     => ['required', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'course_class_id'=> ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'timetables'     => 'array',
        ]);

        $sectionId = $validated['section_id'];

        // ── Teacher clash detection ───────────────────────────────────────────
        $days = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $clashes = [];
        foreach ($validated['timetables'] as $item) {
            if (empty($item['staff_id']) || empty($item['subject_id'])) continue;

            $clash = \App\Models\Timetable::where('school_id', $schoolId)
                ->where('day_of_week', $item['day_of_week'])
                ->where('period_id', $item['period_id'])
                ->where('staff_id', $item['staff_id'])
                ->where('section_id', '!=', $sectionId)
                ->with(['staff.user', 'section', 'period'])
                ->first();

            if ($clash) {
                $staffName = $clash->staff?->user?->name ?? 'Unknown Teacher';
                $periodName = $clash->period->name ?? "Period {$item['period_id']}";
                $dayName = $days[$item['day_of_week']] ?? $item['day_of_week'];
                $sectionName = $clash->section->name ?? 'another section';
                $clashes[] = "{$staffName} is already assigned on {$dayName} ({$periodName}) to {$sectionName}.";
            }
        }

        if (!empty($clashes)) {
            return back()->withErrors(['clash' => implode(' ', $clashes)])->with('error', 'Teacher clash detected. Please resolve before saving.');
        }
        // ─────────────────────────────────────────────────────────────────────

        DB::transaction(function () use ($validated, $schoolId, $sectionId) {
            foreach ($validated['timetables'] as $item) {
                if (empty($item['subject_id'])) {
                    \App\Models\Timetable::where('school_id', $schoolId)
                        ->where('section_id', $sectionId)
                        ->where('period_id', $item['period_id'])
                        ->where('day_of_week', $item['day_of_week'])
                        ->delete();
                    continue;
                }

                \App\Models\Timetable::updateOrCreate(
                    [
                        'school_id'  => $schoolId,
                        'section_id' => $sectionId,
                        'period_id'  => $item['period_id'],
                        'day_of_week'=> $item['day_of_week'],
                    ],
                    [
                        'course_class_id' => $validated['course_class_id'],
                        'subject_id'      => $item['subject_id'],
                        'staff_id'        => $item['staff_id'],
                    ]
                );
            }
        });

        return back()->with('success', 'Timetable saved successfully');
    }

    public function generate(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->can('edit_schedule') && !$user->isSchoolManagement(), 403, 'You do not have permission to manage timetables.');

        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'section_id'              => ['required', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'course_class_id'         => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'target_day'              => 'nullable|string',
            'target_class_subject_ids'=> 'nullable|array',
            'settings'                => 'nullable|array',
        ]);

        $sectionId = $validated['section_id'];
        $classId   = $validated['course_class_id'];
        $targetDay = $validated['target_day'] ?? null;
        $targetClassSubjectIds = $validated['target_class_subject_ids'] ?? [];
        $settings  = $validated['settings'] ?? [];

        $periods = \App\Models\Period::where('school_id', $schoolId)->where('type', '!=', 'break')->orderBy('order')->get();

        $csQuery = \App\Models\ClassSubject::where('school_id', $schoolId)
            ->where('course_class_id', $classId)
            ->where(function($q) use ($sectionId) {
                $q->where('section_id', $sectionId)->orWhereNull('section_id');
            });

        if (!empty($targetClassSubjectIds)) {
            $csQuery->whereIn('id', $targetClassSubjectIds);
        }

        $classSubjects = $csQuery->get();

        if ($classSubjects->isEmpty() || $periods->isEmpty()) {
            return back()->with('error', 'Please define periods and assign subjects to this section first.');
        }

        $weekdayPeriods = $periods->where('is_weekend', false);
        $weekendPeriods = $periods->where('is_weekend', true);

        $daysMap = [ 1 => false, 2 => false, 3 => false, 4 => false, 5 => false, 6 => true ];
        $days = $targetDay ? [ $targetDay => ($targetDay == 6) ] : $daysMap;

        $lockExisting     = filter_var($settings['lock_existing'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $respectWeeklyLimit = filter_var($settings['respect_weekly_limit'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $enableRandomization = filter_var($settings['enable_randomization'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $avoidSameDay     = filter_var($settings['avoid_same_day'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $avoidConsecutive = filter_var($settings['avoid_consecutive'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $allowDoublePeriods = filter_var($settings['allow_double_periods'] ?? false, FILTER_VALIDATE_BOOLEAN);

        DB::transaction(function () use (
            $schoolId, $sectionId, $classId, $targetDay, $classSubjects, $days,
            $lockExisting, $respectWeeklyLimit, $enableRandomization,
            $avoidSameDay, $avoidConsecutive, $allowDoublePeriods,
            $weekdayPeriods, $weekendPeriods, $targetClassSubjectIds
        ) {
            if (!$lockExisting) {
                $deleteQuery = \App\Models\Timetable::where('school_id', $schoolId)
                    ->where('section_id', $sectionId);

                if ($targetDay) {
                    $deleteQuery->where('day_of_week', $targetDay);
                }
                if (!empty($targetClassSubjectIds)) {
                    $deleteQuery->whereIn('subject_id', $classSubjects->pluck('subject_id'));
                }
                $deleteQuery->delete();
            }

            $subjectsPool = [];
            foreach ($classSubjects as $cs) {
                $multiplier = $respectWeeklyLimit ? 5 : ($targetDay ? 1 : 5);
                for ($i = 0; $i < $multiplier; $i++) {
                    $subjectsPool[] = $cs;
                }
            }

            if ($enableRandomization) {
                shuffle($subjectsPool);
            }

            $poolIndex = 0;

            foreach ($days as $day => $isWeekend) {
                $dayPeriods = $isWeekend ? $weekendPeriods : $weekdayPeriods;

                foreach ($dayPeriods as $period) {
                    $occupied = \App\Models\Timetable::where('school_id', $schoolId)
                        ->where('section_id', $sectionId)
                        ->where('day_of_week', $day)
                        ->where('period_id', $period->id)
                        ->exists();

                    if ($occupied) continue;

                    $assigned = false;
                    $attempts = 0;
                    $maxAttempts = count($subjectsPool);

                    while (!$assigned && $attempts < $maxAttempts) {
                        if ($poolIndex >= count($subjectsPool)) {
                            if ($enableRandomization) shuffle($subjectsPool);
                            $poolIndex = 0;
                        }

                        $cs = $subjectsPool[$poolIndex++];
                        $attempts++;

                        $conflict = false;
                        if ($cs->incharge_staff_id) {
                            $conflict = \App\Models\Timetable::where('school_id', $schoolId)
                                ->where('day_of_week', $day)
                                ->where('period_id', $period->id)
                                ->whereNotNull('staff_id')
                                ->where('staff_id', $cs->incharge_staff_id)
                                ->exists();
                        }

                        if ($conflict) continue;

                        if ($avoidSameDay) {
                           $alreadyToday = \App\Models\Timetable::where('school_id', $schoolId)->where('section_id', $sectionId)->where('day_of_week', $day)->where('subject_id', $cs->subject_id)->exists();
                           if ($alreadyToday) continue;
                        }

                        if ($avoidConsecutive && !$allowDoublePeriods) {
                           $prevPeriod = \App\Models\Period::where('school_id', $schoolId)->where('type', '!=', 'break')->where('order', '<', $period->order)->orderBy('order', 'desc')->first();
                           if ($prevPeriod) {
                               $consecutive = \App\Models\Timetable::where('school_id', $schoolId)->where('section_id', $sectionId)->where('day_of_week', $day)->where('period_id', $prevPeriod->id)->where('subject_id', $cs->subject_id)->exists();
                               if ($consecutive) continue;
                           }
                        }

                        \App\Models\Timetable::create([
                            'school_id'      => $schoolId,
                            'course_class_id'=> $classId,
                            'section_id'     => $sectionId,
                            'period_id'      => $period->id,
                            'day_of_week'    => $day,
                            'subject_id'     => $cs->subject_id,
                            'staff_id'       => $cs->incharge_staff_id,
                        ]);
                        $assigned = true;
                    }
                }
            }
        });

        return back()->with('success', 'Timetable auto-generated successfully. Please review and adjust.');
    }

    public function reset(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->can('edit_schedule') && !$user->isSchoolManagement(), 403, 'You do not have permission to manage timetables.');

        $schoolId = app('current_school_id');
        $validated = $request->validate([
            'section_id' => ['required', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
        ]);

        \App\Models\Timetable::where('school_id', $schoolId)
            ->where('section_id', $validated['section_id'])
            ->delete();

        return back()->with('success', 'Timetable has been completely reset.');
    }
}
