<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\CourseClass;
use App\Models\Department;
use App\Models\Staff;
use Inertia\Inertia;

class CourseClassController extends Controller
{
    public function index()
    {
        $school      = app('current_school');
        $classes     = CourseClass::with('department', 'inchargeStaff.user')
            ->withCount('sections')
            ->where('school_id', $school->id)
            ->orderBy('numeric_value')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id'               => $c->id,
                'name'             => $c->name,
                'numeric_value'    => $c->numeric_value,
                'department_id'    => $c->department_id,
                'incharge_staff_id'=> $c->incharge_staff_id,
                'sections_count'   => $c->sections_count,
                'department'       => $c->department ? ['id' => $c->department->id, 'name' => $c->department->name] : null,
                'incharge_staff'   => $c->inchargeStaff ? ['id' => $c->inchargeStaff->id, 'name' => optional($c->inchargeStaff->user)->name] : null,
            ]);

        $departments = Department::where('school_id', $school->id)->get(['id', 'name']);
        $staff       = Staff::with('user')
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => optional($s->user)->name]);

        return Inertia::render('School/Academics/Classes', [
            'classes'     => $classes,
            'departments' => $departments,
            'staff'       => $staff,
        ]);
    }

    public function store(Request $request)
    {
        $school    = app('current_school');
        $validated = $request->validate([
            'department_id'     => ['required', Rule::exists('departments', 'id')->where('school_id', $school->id)],
            'name'              => [
                'required', 'string', 'max:255',
                Rule::unique('course_classes')->where(fn ($q) => $q->where('school_id', $school->id)->whereNull('deleted_at')),
            ],
            'numeric_value'     => 'nullable|integer|min:0',
            'incharge_staff_id' => ['nullable', Rule::exists('staff', 'id')->where('school_id', $school->id)],
        ]);
        $validated['school_id'] = $school->id;
        CourseClass::create($validated);

        return redirect()->back()->with('status', 'Class created successfully.');
    }

    public function update(Request $request, CourseClass $class)
    {
        if ($class->school_id !== app('current_school')->id) abort(403);
        $school = app('current_school');

        $validated = $request->validate([
            'department_id'     => ['required', Rule::exists('departments', 'id')->where('school_id', $school->id)],
            'name'              => [
                'required', 'string', 'max:255',
                Rule::unique('course_classes')
                    ->where(fn ($q) => $q->where('school_id', $school->id)->whereNull('deleted_at'))
                    ->ignore($class->id),
            ],
            'numeric_value'     => 'nullable|integer|min:0',
            'incharge_staff_id' => ['nullable', Rule::exists('staff', 'id')->where('school_id', $school->id)],
        ]);
        $class->update($validated);

        return redirect()->back()->with('status', 'Class updated successfully.');
    }

    public function destroy(CourseClass $class)
    {
        if ($class->school_id !== app('current_school')->id) abort(403);
        $class->delete();
        return redirect()->back()->with('status', 'Class deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $school = app('current_school');
        foreach ($request->input('order', []) as $item) {
            CourseClass::where('id', $item['id'])->where('school_id', $school->id)
                ->update(['numeric_value' => $item['order']]);
        }
        return response()->json(['ok' => true]);
    }
}
