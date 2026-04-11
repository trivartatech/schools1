<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentHealthController extends Controller
{
    /**
     * Show the health record form for a student.
     */
    public function edit(Student $student)
    {
        abort_if($student->school_id !== app('current_school_id'), 403);

        if (auth()->user()->isParent()) {
            abort_unless($student->parent_id === auth()->user()->studentParent?->id, 403, 'Unauthorized access.');
        } elseif (auth()->user()->isStudent()) {
            abort_unless($student->id === auth()->user()->student?->id, 403, 'Unauthorized access.');
        }

        $student->load('healthRecord');

        return Inertia::render('School/Students/Health', [
            'student' => $student,
        ]);
    }

    /**
     * Save or update the health record for a student.
     */
    public function update(Request $request, Student $student)
    {
        abort_if($student->school_id !== app('current_school_id'), 403);

        if (auth()->user()->isParent()) {
            abort_unless($student->parent_id === auth()->user()->studentParent?->id, 403, 'Unauthorized access.');
        } elseif (auth()->user()->isStudent()) {
            abort_unless($student->id === auth()->user()->student?->id, 403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            // Physical
            'height_cm'                  => 'nullable|numeric|min:0|max:300',
            'weight_kg'                  => 'nullable|numeric|min:0|max:300',
            'vision_left'                => 'nullable|string|max:20',
            'vision_right'               => 'nullable|string|max:20',
            'hearing'                    => 'nullable|string|max:50',

            // Medical
            'known_allergies'            => 'nullable|string',
            'chronic_conditions'         => 'nullable|string',
            'current_medications'        => 'nullable|string',
            'past_surgeries'             => 'nullable|string',
            'disability'                 => 'nullable|string|max:100',
            'special_needs'              => 'nullable|string|max:255',

            // Vaccinations
            'vaccinations'               => 'nullable|array',
            'vaccinations.*.name'        => 'required|string|max:100',
            'vaccinations.*.date'        => 'nullable|date',
            'vaccinations.*.dose'        => 'nullable|string|max:50',
            'vaccinations.*.notes'       => 'nullable|string|max:255',

            // Emergency contact
            'emergency_contact_name'     => 'nullable|string|max:255',
            'emergency_contact_phone'    => 'nullable|string|max:20',
            'emergency_contact_relation' => 'nullable|string|max:100',

            // Doctor
            'family_doctor_name'         => 'nullable|string|max:255',
            'family_doctor_phone'        => 'nullable|string|max:20',
            'remarks'                    => 'nullable|string',
        ]);

        $student->healthRecord()->updateOrCreate(
            ['student_id' => $student->id],
            array_merge($validated, ['school_id' => app('current_school_id')])
        );

        return redirect()->route('school.students.show', $student->id)
                         ->with('success', 'Health record saved successfully.');
    }
}
