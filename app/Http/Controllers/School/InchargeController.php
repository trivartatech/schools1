<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\CourseClass;
use App\Models\Section;
use App\Models\ClassSubject;
use App\Models\Staff;
use App\Services\TeacherScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InchargeController extends Controller
{
    /**
     * Show the incharge assignment drag-and-drop page.
     */
    public function index()
    {
        $schoolId = app('current_school_id');

        // Load all classes with their sections, incharges, and subjects
        $classes = CourseClass::with([
            'inchargeStaff.user',
            'sections.inchargeStaff.user',
            'sections.inchargeStaff',
        ])
        ->where('school_id', $schoolId)
        ->orderBy('numeric_value')->orderBy('name')
        ->get();

        // Load class-subjects (subjects assigned to each class-section)
        $classSubjects = ClassSubject::with([
            'subject',
            'section',
            'courseClass',
            'inchargeStaff.user',
        ])
        ->where('school_id', $schoolId)
        ->get();

        // Load active staff with their user data
        $staff = Staff::with('user', 'designation')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(fn ($s) => [
                'id'          => $s->id,
                'name'        => $s->user->name ?? 'Unknown',
                'employee_id' => $s->employee_id,
                'designation' => $s->designation->name ?? null,
                'photo'       => $s->photo,
            ]);

        return Inertia::render('School/Incharge/Index', [
            'classes'       => $classes,
            'classSubjects' => $classSubjects,
            'staff'         => $staff,
        ]);
    }

    /**
     * Assign class incharge (drag-and-drop drop on a class).
     */
    public function assignClassIncharge(Request $request, CourseClass $class)
    {
        abort_if($class->school_id !== app('current_school_id'), 403);

        $request->validate([
            'staff_id' => [
                'nullable',
                Rule::exists('staff', 'id')
                    ->where('school_id', app('current_school_id'))
                    ->where('status', 'active'),
            ],
        ]);

        $previousStaffId = $class->incharge_staff_id;
        $class->update(['incharge_staff_id' => $request->staff_id]);

        $this->clearScopeCaches($previousStaffId, $request->staff_id);

        activity('incharge')
            ->causedBy(auth()->user())
            ->performedOn($class)
            ->withProperties([
                'previous_staff_id' => $previousStaffId,
                'staff_id'          => $request->staff_id,
            ])
            ->log($request->staff_id ? 'Class incharge assigned' : 'Class incharge removed');

        return back()->with('success', $request->staff_id
            ? 'Class incharge assigned successfully.'
            : 'Class incharge removed.');
    }

    /**
     * Assign section incharge (drag-and-drop drop on a section).
     */
    public function assignSectionIncharge(Request $request, Section $section)
    {
        abort_if($section->school_id !== app('current_school_id'), 403);

        $request->validate([
            'staff_id' => [
                'nullable',
                Rule::exists('staff', 'id')
                    ->where('school_id', app('current_school_id'))
                    ->where('status', 'active'),
            ],
        ]);

        $previousStaffId = $section->incharge_staff_id;
        $section->update(['incharge_staff_id' => $request->staff_id]);

        // Section chat-group membership is auto-synced by SectionObserver
        // when `incharge_staff_id` changes — no manual sync needed here.

        $this->clearScopeCaches($previousStaffId, $request->staff_id);

        activity('incharge')
            ->causedBy(auth()->user())
            ->performedOn($section)
            ->withProperties([
                'previous_staff_id' => $previousStaffId,
                'staff_id'          => $request->staff_id,
            ])
            ->log($request->staff_id ? 'Section incharge assigned' : 'Section incharge removed');

        return back()->with('success', $request->staff_id
            ? 'Section incharge assigned successfully.'
            : 'Section incharge removed.');
    }

    /**
     * Assign subject incharge (drag-and-drop drop on a class-subject).
     */
    public function assignSubjectIncharge(Request $request, ClassSubject $classSubject)
    {
        abort_if($classSubject->school_id !== app('current_school_id'), 403);

        $request->validate([
            'staff_id' => [
                'nullable',
                Rule::exists('staff', 'id')
                    ->where('school_id', app('current_school_id'))
                    ->where('status', 'active'),
            ],
        ]);

        $previousStaffId = $classSubject->incharge_staff_id;
        $classSubject->update(['incharge_staff_id' => $request->staff_id]);

        $this->clearScopeCaches($previousStaffId, $request->staff_id);

        activity('incharge')
            ->causedBy(auth()->user())
            ->performedOn($classSubject)
            ->withProperties([
                'previous_staff_id' => $previousStaffId,
                'staff_id'          => $request->staff_id,
            ])
            ->log($request->staff_id ? 'Subject incharge assigned' : 'Subject incharge removed');

        return back()->with('success', $request->staff_id
            ? 'Subject incharge assigned successfully.'
            : 'Subject incharge removed.');
    }

    /**
     * Clear TeacherScopeService cache for both the old and new staff.
     */
    private function clearScopeCaches(?int $previousStaffId, ?int $newStaffId): void
    {
        $service  = app(TeacherScopeService::class);
        $schoolId = app('current_school_id');

        foreach (array_unique(array_filter([$previousStaffId, $newStaffId])) as $staffId) {
            $service->clearCache($staffId, $schoolId);
        }
    }
}
