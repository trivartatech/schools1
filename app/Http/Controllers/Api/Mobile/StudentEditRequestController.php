<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\EditRequest;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Allows teachers and admins to submit a profile edit request for any
 * student in their school — including a passport photo.
 *
 * POST /mobile/students/{student}/edit-request
 */
class StudentEditRequestController extends Controller
{
    private const ALLOWED_ROLES = ['admin', 'super_admin', 'principal', 'school_admin', 'teacher'];

    public function store(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        $type = $user->user_type instanceof \BackedEnum
            ? $user->user_type->value
            : (string) $user->user_type;

        abort_unless(
            in_array($type, self::ALLOWED_ROLES, true),
            response()->json(['message' => 'Not authorised to submit edit requests.'], 403)
        );

        // Tenant scope
        abort_unless(
            (int) $student->school_id === (int) app('current_school_id'),
            response()->json(['message' => 'Student not found.'], 404)
        );

        $student->load('studentParent');

        $validated = $request->validate([
            // Passport photo
            'photo'               => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
            // Student identity
            'first_name'          => 'nullable|string|max:255',
            'last_name'           => 'nullable|string|max:255',
            'dob'                 => 'nullable|date',
            'birth_place'         => 'nullable|string|max:255',
            'mother_tongue'       => 'nullable|string|max:50',
            'blood_group'         => 'nullable|string|max:10',
            'religion'            => 'nullable|string|max:50',
            'caste'               => 'nullable|string|max:50',
            'category'            => 'nullable|string|max:50',
            'aadhaar_no'          => 'nullable|string|max:20',
            'address'             => 'nullable|string',
            // Parent/guardian
            'primary_phone'       => 'nullable|string|max:20',
            'father_name'         => 'nullable|string|max:255',
            'mother_name'         => 'nullable|string|max:255',
            'guardian_name'       => 'nullable|string|max:255',
            'guardian_email'      => 'nullable|email|max:255',
            'guardian_phone'      => 'nullable|string|max:20',
            'father_phone'        => 'nullable|string|max:20',
            'mother_phone'        => 'nullable|string|max:20',
            'father_occupation'   => 'nullable|string|max:255',
            'father_qualification'=> 'nullable|string|max:100',
            'mother_occupation'   => 'nullable|string|max:255',
            'mother_qualification'=> 'nullable|string|max:100',
            'parent_address'      => 'nullable|string',
            'reason'              => 'nullable|string|max:1000',
        ]);

        $reason = $validated['reason'] ?? null;
        unset($validated['reason'], $validated['photo']);

        $requestedChanges = [];

        $checkVal = function ($key, $oldVal, $newVal) use (&$requestedChanges) {
            if ($newVal === '') $newVal = null;
            if ($newVal !== null && (string) $oldVal !== (string) $newVal) {
                $requestedChanges[$key] = $newVal;
            }
        };

        // Student fields
        $checkVal('first_name',    $student->first_name,              $validated['first_name']    ?? null);
        $checkVal('last_name',     $student->last_name,               $validated['last_name']     ?? null);
        $checkVal('dob',           $student->dob?->toDateString(),    $validated['dob']           ?? null);
        $checkVal('birth_place',   $student->birth_place,             $validated['birth_place']   ?? null);
        $checkVal('mother_tongue', $student->mother_tongue,           $validated['mother_tongue'] ?? null);
        $checkVal('blood_group',   $student->blood_group,             $validated['blood_group']   ?? null);
        $checkVal('religion',      $student->religion,                $validated['religion']      ?? null);
        $checkVal('caste',         $student->caste,                   $validated['caste']         ?? null);
        $checkVal('category',      $student->category,                $validated['category']      ?? null);
        $checkVal('aadhaar_no',    $student->aadhaar_no,              $validated['aadhaar_no']    ?? null);
        $checkVal('address',       $student->address,                 $validated['address']       ?? null);

        // Parent fields
        if ($student->studentParent) {
            $p = $student->studentParent;
            $checkVal('primary_phone',      $p->primary_phone,      $validated['primary_phone']      ?? null);
            $checkVal('father_name',        $p->father_name,        $validated['father_name']        ?? null);
            $checkVal('mother_name',        $p->mother_name,        $validated['mother_name']        ?? null);
            $checkVal('guardian_name',      $p->guardian_name,      $validated['guardian_name']      ?? null);
            $checkVal('guardian_email',     $p->guardian_email,     $validated['guardian_email']     ?? null);
            $checkVal('guardian_phone',     $p->guardian_phone,     $validated['guardian_phone']     ?? null);
            $checkVal('father_phone',       $p->father_phone,       $validated['father_phone']       ?? null);
            $checkVal('mother_phone',       $p->mother_phone,       $validated['mother_phone']       ?? null);
            $checkVal('father_occupation',  $p->father_occupation,  $validated['father_occupation']  ?? null);
            $checkVal('father_qualification',$p->father_qualification,$validated['father_qualification'] ?? null);
            $checkVal('mother_occupation',  $p->mother_occupation,  $validated['mother_occupation']  ?? null);
            $checkVal('mother_qualification',$p->mother_qualification,$validated['mother_qualification'] ?? null);
            $checkVal('parent_address',     $p->address,            $validated['parent_address']     ?? null);
        }

        // Photo is always a new change when provided
        if ($request->hasFile('photo')) {
            $requestedChanges['photo'] = $request->file('photo')
                ->store('edit-requests/photos', 'public');
        }

        if (empty($requestedChanges)) {
            return response()->json([
                'message' => 'No changes detected. Please modify at least one field.',
            ], 422);
        }

        $editRequest = EditRequest::create([
            'school_id'         => app('current_school_id'),
            'user_id'           => $user->id,
            'requestable_type'  => Student::class,
            'requestable_id'    => $student->id,
            'requested_changes' => $requestedChanges,
            'reason'            => $reason,
            'status'            => 'pending',
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Profile update request submitted. Pending admin approval.',
            'edit_request' => $editRequest,
        ], 201);
    }
}
