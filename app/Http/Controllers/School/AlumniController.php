<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\AcademicYear;
use App\Models\CourseClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AlumniController extends Controller
{
    // ── Alumni directory ──────────────────────────────────────────────────
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');

        $query = Alumni::where('alumni.school_id', $schoolId)
            ->with(['student:id,first_name,last_name,admission_no,photo,gender,dob'])
            ->join('students', 'alumni.student_id', '=', 'students.id');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('students.first_name', 'like', "%$s%")
                  ->orWhere('students.last_name',  'like', "%$s%")
                  ->orWhere('students.admission_no','like', "%$s%")
                  ->orWhere('alumni.current_occupation', 'like', "%$s%");
            });
        }

        if ($request->filled('passout_year')) {
            $query->where('alumni.passout_year', $request->passout_year);
        }

        if ($request->filled('final_class')) {
            $query->where('alumni.final_class', $request->final_class);
        }

        $alumni = $query->select('alumni.*')->orderByDesc('alumni.passout_year')->paginate(30)->withQueryString();

        $years        = Alumni::where('school_id', $schoolId)->distinct()->orderByDesc('passout_year')->pluck('passout_year');
        $classes      = Alumni::where('school_id', $schoolId)->distinct()->orderBy('final_class')->pluck('final_class');
        $schoolClasses = CourseClass::where('school_id', $schoolId)->orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('School/Alumni/Index', compact('alumni', 'years', 'classes', 'schoolClasses'));
    }

    // ── Graduate a student (create alumni record) ─────────────────────────
    public function graduate(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $validated = $request->validate([
            'student_ids'       => 'required|array|min:1',
            'student_ids.*'     => 'required|integer|exists:students,id',
            'final_class'       => 'nullable|string|max:100',
            'passout_year'      => 'nullable|string|max:9',
            'final_percentage'  => 'nullable|numeric|min:0|max:100',
            'final_grade'       => 'nullable|string|max:5',
            'graduated_on'      => 'nullable|date',
            'notes'             => 'nullable|string',
        ]);

        $passoutYear = $validated['passout_year'] ?? (AcademicYear::find($academicYearId)?->name);
        $graduated   = 0;
        $skipped     = 0;

        foreach ($validated['student_ids'] as $studentId) {
            $student = Student::where('id', $studentId)->where('school_id', $schoolId)->first();
            if (!$student) continue;

            if (Alumni::where('student_id', $student->id)->exists()) {
                $skipped++;
                continue;
            }

            Alumni::create([
                'school_id'        => $schoolId,
                'student_id'       => $student->id,
                'academic_year_id' => $academicYearId,
                'final_class'      => $validated['final_class'],
                'passout_year'     => $passoutYear,
                'final_percentage' => $validated['final_percentage'],
                'final_grade'      => $validated['final_grade'],
                'graduated_on'     => $validated['graduated_on'] ?? now()->toDateString(),
                'graduated_by'     => auth()->id(),
                'notes'            => $validated['notes'],
            ]);

            $student->update(['status' => 'graduated']);
            $graduated++;
        }

        $msg = "{$graduated} student(s) graduated successfully.";
        if ($skipped > 0) $msg .= " {$skipped} already in alumni (skipped).";

        return back()->with('success', $msg);
    }

    // ── Update alumni profile (post-school info) ─────────────────────────
    public function update(Request $request, Alumni $alumnus)
    {
        abort_if($alumnus->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'current_occupation' => 'nullable|string|max:150',
            'current_employer'   => 'nullable|string|max:150',
            'current_city'       => 'nullable|string|max:100',
            'current_state'      => 'nullable|string|max:100',
            'personal_email'     => 'nullable|email|max:150',
            'personal_phone'     => 'nullable|string|max:20',
            'linkedin_url'       => 'nullable|url|max:255',
            'achievements'       => 'nullable|string',
            'notes'              => 'nullable|string',
            'final_percentage'   => 'nullable|numeric|min:0|max:100',
            'final_grade'        => 'nullable|string|max:5',
        ]);

        $alumnus->update($validated);
        return back()->with('success', 'Alumni profile updated.');
    }

    // ── Remove from alumni (e.g., data entry error) ───────────────────────
    public function destroy(Alumni $alumnus)
    {
        abort_if($alumnus->school_id !== app('current_school_id'), 403);

        // Revert student status
        $alumnus->student?->update(['status' => 'active']);
        $alumnus->delete();

        return back()->with('success', 'Removed from alumni records.');
    }

    // ── Search students to graduate (AJAX / Inertia) ──────────────────────
    public function searchStudents(Request $request)
    {
        $schoolId       = app('current_school_id');
        $classId        = $request->get('class_id');
        $q              = $request->get('q', '');

        $students = Student::where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereDoesntHave('alumni')
            ->when($classId, fn($query) => $query->whereHas('currentAcademicHistory',
                fn($h) => $h->where('class_id', $classId)
            ))
            ->when($q, fn($query) => $query->where(function ($inner) use ($q) {
                $inner->where('first_name', 'like', "%{$q}%")
                      ->orWhere('last_name',  'like', "%{$q}%")
                      ->orWhere('admission_no','like', "%{$q}%");
            }))
            ->with(['currentAcademicHistory' => fn($h) => $h->with('courseClass', 'section')])
            ->orderBy('first_name')
            ->limit(80)
            ->get(['id', 'first_name', 'last_name', 'admission_no']);

        return response()->json($students);
    }
}
