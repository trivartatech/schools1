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
        $academicYearId = session('current_academic_year_id');

        // Load all classes with their sections, incharges, and subjects
        $classes = CourseClass::with([
            'inchargeStaff.user',
            'sections.inchargeStaff.user',
            'sections.inchargeStaff',
        ])
        ->where('school_id', $schoolId)
        ->orderBy('numeric_value')
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
            'staff_id' => ['nullable', Rule::exists('staff', 'id')->where('school_id', app('current_school_id'))],
        ]);

        $class->update(['incharge_staff_id' => $request->staff_id]);

        // Clear teacher scope cache so the change takes effect immediately
        if ($request->staff_id) {
            $staff = Staff::find($request->staff_id);
            if ($staff) app(TeacherScopeService::class)->clearCache($staff->id, $staff->school_id);
        }

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
            'staff_id' => ['nullable', Rule::exists('staff', 'id')->where('school_id', app('current_school_id'))],
        ]);

        $section->update(['incharge_staff_id' => $request->staff_id]);

        // Sync teacher to section chat group
        if ($request->staff_id) {
            $staff = Staff::with('user')->find($request->staff_id);
            if ($staff?->user_id) {
                $conv = \App\Models\ChatConversation::where('section_id', $section->id)
                    ->where('group_type', 'section_group')
                    ->first();
                if ($conv) {
                    \App\Models\ChatParticipant::updateOrCreate(
                        ['conversation_id' => $conv->id, 'user_id' => $staff->user_id],
                        ['role' => 'admin', 'joined_at' => now()]
                    );
                }
            }
            // Clear teacher scope cache
            app(TeacherScopeService::class)->clearCache($staff->id, $staff->school_id);
        }

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
            'staff_id' => ['nullable', Rule::exists('staff', 'id')->where('school_id', app('current_school_id'))],
        ]);

        $classSubject->update(['incharge_staff_id' => $request->staff_id]);

        // Clear teacher scope cache
        if ($request->staff_id) {
            $staff = Staff::find($request->staff_id);
            if ($staff) app(TeacherScopeService::class)->clearCache($staff->id, $staff->school_id);
        }

        return back()->with('success', $request->staff_id
            ? 'Subject incharge assigned successfully.'
            : 'Subject incharge removed.');
    }
}
