<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $schoolId = app('current_school_id');

        return [
            // Academic — scoped to current school to prevent cross-tenant FK injection
            'class_id'   => ['required', Rule::exists('course_classes', 'id')->where('school_id', $schoolId)],
            'section_id' => ['required', Rule::exists('sections', 'id')->where('school_id', $schoolId)],
            'student_type' => 'nullable|in:New Student,Old Student',

            // Student Personal
            'first_name'               => 'required|string|max:255',
            'last_name'                => 'nullable|string|max:255',
            'dob'                      => 'required|date',
            'birth_place'              => 'nullable|string|max:255',
            'mother_tongue'            => 'nullable|string|max:50',
            'gender'                   => 'required|in:Male,Female,Other',
            'blood_group'              => 'nullable|string|max:10',
            'religion'                 => 'nullable|string|max:50',
            'caste'                    => 'nullable|string|max:50',
            'category'                 => 'nullable|string|max:50',
            'aadhaar_no'               => 'nullable|digits:12',
            'photo'                    => 'nullable|image|max:5120',
            'student_address'          => 'nullable|string',

            // Student extras
            'nationality'              => 'nullable|string|max:100',
            'city'                     => 'nullable|string|max:100',
            'state'                    => 'nullable|string|max:100',
            'pincode'                  => 'nullable|string|max:20',
            'emergency_contact_name'   => 'nullable|string|max:255',
            'emergency_contact_phone'  => 'nullable|string|max:20',

            // Parent/Guardian
            'primary_phone'            => 'required|string|max:20',
            'father_name'              => 'nullable|string|max:255',
            'mother_name'              => 'nullable|string|max:255',
            'guardian_name'            => 'nullable|string|max:255',
            'guardian_email'           => 'nullable|email|max:255',
            'guardian_phone'           => 'nullable|string|max:20',
            'father_phone'             => 'nullable|string|max:20',
            'mother_phone'             => 'nullable|string|max:20',
            'father_occupation'        => 'nullable|string|max:255',
            'father_qualification'     => 'nullable|string|max:100',
            'mother_occupation'        => 'nullable|string|max:255',
            'mother_qualification'     => 'nullable|string|max:100',
            'parent_address'           => 'nullable|string',

            // Transport (optional) — scoped to school
            'transport_route_id'       => ['nullable', Rule::exists('transport_routes', 'id')->where('school_id', $schoolId)],
            'transport_stop_id'        => 'nullable|exists:transport_stops,id',
            'transport_pickup_type'    => 'nullable|in:pickup,drop,both',
            'transport_months'         => 'nullable|integer|min:0|max:24',
            'transport_days'           => 'nullable|integer|min:0|max:30',
        ];
    }

    public function messages(): array
    {
        return [
            'class_id.required'    => 'Please select a class.',
            'section_id.required'  => 'Please select a section.',
            'first_name.required'  => 'Student first name is required.',
            'dob.required'         => 'Date of birth is required.',
            'gender.required'      => 'Gender is required.',
            'primary_phone.required' => 'Primary contact phone is required.',
            'aadhaar_no.digits'    => 'Aadhaar number must be exactly 12 digits.',
            'photo.image'          => 'Photo must be an image file.',
            'photo.max'            => 'Photo must not exceed 5MB.',
        ];
    }
}
