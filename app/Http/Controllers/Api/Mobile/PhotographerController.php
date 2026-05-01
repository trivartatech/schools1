<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\School\PhotoNumberController;
use App\Models\CourseClass;
use App\Models\EditRequest;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mobile API for the school photographer flow.
 *
 * The photographer logs into the mobile app with a per-school synthetic
 * credential (see PhotoNumberController::generatePhotographerCredential)
 * and uses these endpoints to:
 *   - browse the roster of students by class/section
 *   - record a photo number against each student
 *   - request profile edits (which queue as EditRequests just like the web
 *     pencil-edit modal does — they don't directly mutate the profile)
 *
 * The route group applies `auth:sanctum`, `tenant`, `ability:photographer`,
 * and `ensure.photographer` middleware, so all endpoints can assume:
 *   - request->user() is non-null
 *   - it's either the school's photographer or an admin/super_admin
 *   - the tenant container bindings (current_school_id, current_academic_year_id)
 *     are set
 */
class PhotographerController extends Controller
{
    public function classes(Request $request): JsonResponse
    {
        $schoolId = app('current_school_id');

        $classes = CourseClass::where('school_id', $schoolId)
            ->orderBy('numeric_value')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['classes' => $classes]);
    }

    public function sections(Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => ['required', 'integer', 'exists:course_classes,id'],
        ]);

        $schoolId      = app('current_school_id');
        $currentYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $sections = Section::where('school_id', $schoolId)
            ->where('course_class_id', $request->integer('class_id'))
            ->when($currentYearId, fn($q) => $q->forYear($currentYearId))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['sections' => $sections]);
    }

    /**
     * Roster for a class (+ optional section). Mirrors the payload built by
     * PhotoNumberController@index so mobile and web row shapes are identical.
     */
    public function roster(Request $request): JsonResponse
    {
        $request->validate([
            'class_id'   => ['required', 'integer', 'exists:course_classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
        ]);

        $schoolId      = app('current_school_id');
        $currentYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        if (! $currentYearId) {
            return response()->json(['students' => []]);
        }

        $query = StudentAcademicHistory::with([
                'student:id,first_name,last_name,admission_no,erp_no,photo,gender,address,parent_id,photo_number',
                'student.studentParent:id,father_name,mother_name,father_phone,mother_phone,primary_phone,address',
                'courseClass:id,name',
                'section:id,name',
            ])
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $currentYearId)
            ->where('class_id', $request->integer('class_id'));

        if ($sectionId = $request->integer('section_id')) {
            $query->where('section_id', $sectionId);
        }

        $histories = $query->get()->filter(fn($h) => $h->student !== null);

        $studentIds = $histories->pluck('student_id')->all();
        $pendingByStudent = [];
        if (! empty($studentIds)) {
            $pendingByStudent = EditRequest::tenant()
                ->where('requestable_type', Student::class)
                ->whereIn('requestable_id', $studentIds)
                ->where('status', 'pending')
                ->get(['requestable_id', 'requested_changes'])
                ->groupBy('requestable_id')
                ->map(fn($group) => $group->reduce(
                    fn($carry, $r) => $carry + count($r->requested_changes ?? []),
                    0
                ))
                ->all();
        }

        $students = $histories->map(function ($h) use ($pendingByStudent) {
            $s = $h->student;
            $p = $s->studentParent;

            return [
                'history_id'      => $h->id,
                'student_id'      => $s->id,
                'name'            => trim($s->first_name . ' ' . $s->last_name),
                'first_name'      => $s->first_name,
                'last_name'       => $s->last_name,
                'admission_no'    => $s->admission_no,
                'erp_no'          => $s->erp_no,
                'gender'          => $s->gender,
                'photo_url'       => $s->photo_url,
                'photo_number'    => $s->photo_number ?? '',
                'class_id'        => $h->class_id,
                'class_name'      => $h->courseClass?->name,
                'section_id'      => $h->section_id,
                'section_name'    => $h->section?->name,
                'student_address' => $s->address,
                'primary_phone'   => $p?->primary_phone,
                'father_name'     => $p?->father_name,
                'mother_name'     => $p?->mother_name,
                'father_phone'    => $p?->father_phone,
                'mother_phone'    => $p?->mother_phone,
                'parent_address'  => $p?->address,
                'pending_changes_count' => $pendingByStudent[$s->id] ?? 0,
            ];
        })->sortBy(fn($s) => $s['name'])->values()->all();

        return response()->json(['students' => $students]);
    }

    /**
     * Bulk save photo numbers. Mirrors PhotoNumberController@save except
     * returns JSON (no redirect) since this is a mobile API.
     */
    public function savePhotoNumbers(Request $request): JsonResponse
    {
        $request->validate([
            'assignments'                => ['required', 'array', 'min:1'],
            'assignments.*.student_id'   => ['required', 'integer'],
            'assignments.*.photo_number' => ['nullable', 'string', 'max:50'],
        ]);

        $schoolId = app('current_school_id');

        $photoNos = collect($request->assignments)
            ->pluck('photo_number')
            ->filter()
            ->map(fn($n) => trim($n));

        if ($photoNos->count() !== $photoNos->unique()->count()) {
            return response()->json([
                'message' => 'Duplicate photo numbers detected. Each photo number must be unique within the class/section.',
                'errors'  => [
                    'assignments' => ['Duplicate photo numbers in this batch.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($request, $schoolId) {
            foreach ($request->assignments as $row) {
                $photoNo = ($row['photo_number'] ?? '') !== '' ? trim($row['photo_number']) : null;

                Student::where('id', $row['student_id'])
                    ->where('school_id', $schoolId) // tenant guard
                    ->update(['photo_number' => $photoNo]);
            }
        });

        return response()->json([
            'message' => 'Photo numbers saved successfully.',
            'count'   => count($request->assignments),
        ]);
    }

    /**
     * Queue an EditRequest for the given student. Reuses the exact same diff
     * + create-EditRequest logic the web inline modal calls into, so the
     * pending request that an admin sees at /school/edit-requests is
     * indistinguishable in shape from one created on the web.
     */
    public function requestEdit(Request $request, Student $student): JsonResponse
    {
        abort_if($student->school_id !== app('current_school_id'), 403);

        return app(PhotoNumberController::class)
            ->buildEditRequestResponse($request, $student);
    }
}
