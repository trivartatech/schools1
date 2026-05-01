<?php

namespace App\Http\Controllers\School;

use App\Exports\PendingStudentEditsExport;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseClass;
use App\Models\EditRequest;
use App\Models\Section;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PhotoNumberController extends Controller
{
    /**
     * Field labels used in the export and (eventually) the diff UI when a
     * change comes in via the inline modal. Keeps a single source of truth.
     */
    private const FIELD_LABELS = [
        'first_name'     => 'First Name',
        'last_name'      => 'Last Name',
        'address'        => 'Student Address',
        'father_name'    => 'Father Name',
        'mother_name'    => 'Mother Name',
        'father_phone'   => 'Father Phone',
        'mother_phone'   => 'Mother Phone',
        'parent_address' => 'Parent Address',
    ];

    // ── Index ─────────────────────────────────────────────────

    public function index(Request $request)
    {
        $schoolId      = app('current_school_id');
        $currentYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $academicYears = AcademicYear::where('school_id', $schoolId)
            ->orderByDesc('start_date')->get(['id', 'name', 'is_current']);

        $classes = CourseClass::where('school_id', $schoolId)
            ->orderBy('numeric_value')->orderBy('name')->get(['id', 'name']);

        $yearId    = $request->integer('academic_year_id', $currentYearId);
        $classId   = $request->integer('class_id', 0);
        $sectionId = $request->integer('section_id', 0);

        $sections = $classId
            ? Section::where('school_id', $schoolId)->where('course_class_id', $classId)
                ->forYear($yearId)
                ->orderBy('name')->get(['id', 'name'])
            : collect();

        $students = [];
        $pendingByStudent = [];

        if ($classId && $yearId) {
            $query = StudentAcademicHistory::with([
                    'student:id,first_name,last_name,admission_no,photo,gender,address,parent_id,photo_number',
                    'student.studentParent:id,father_name,mother_name,father_phone,mother_phone,address',
                    'courseClass:id,name',
                    'section:id,name',
                ])
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $yearId)
                ->where('class_id', $classId);

            if ($sectionId) {
                $query->where('section_id', $sectionId);
            }

            $histories = $query->get()->filter(fn($h) => $h->student !== null);

            // Pre-load pending edit-request counts for the visible students so
            // the page can render the "Pending edit" pill without an extra
            // round-trip per row.
            $studentIds = $histories->pluck('student_id')->all();
            if (! empty($studentIds)) {
                $pendingByStudent = EditRequest::tenant()
                    ->where('requestable_type', Student::class)
                    ->whereIn('requestable_id', $studentIds)
                    ->where('status', 'pending')
                    ->get(['requestable_id', 'requested_changes'])
                    ->groupBy('requestable_id')
                    ->map(fn($group) => $group->reduce(function ($carry, $r) {
                        return $carry + count($r->requested_changes ?? []);
                    }, 0))
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
                    'gender'          => $s->gender,
                    'photo_url'       => $s->photo_url,
                    'photo_number'    => $s->photo_number ?? '',
                    'class_name'      => $h->courseClass?->name,
                    'section_name'    => $h->section?->name,
                    'student_address' => $s->address,
                    'father_name'     => $p?->father_name,
                    'mother_name'     => $p?->mother_name,
                    'father_phone'    => $p?->father_phone,
                    'mother_phone'    => $p?->mother_phone,
                    'parent_address'  => $p?->address,
                    'pending_changes_count' => $pendingByStudent[$s->id] ?? 0,
                ];
            })->sortBy(fn($s) => $s['name'])->values()->all();
        }

        return Inertia::render('School/Students/PhotoNumbers/Index', [
            'academicYears' => $academicYears,
            'classes'       => $classes,
            'sections'      => $sections,
            'students'      => $students,
            'filters'       => [
                'academic_year_id' => $yearId,
                'class_id'         => $classId,
                'section_id'       => $sectionId,
            ],
        ]);
    }

    // ── Save photo numbers (direct write — internal tracking field) ─────

    public function save(Request $request)
    {
        $schoolId = app('current_school_id');

        $request->validate([
            'assignments'                 => ['required', 'array', 'min:1'],
            'assignments.*.student_id'    => ['required', 'integer'],
            'assignments.*.photo_number'  => ['nullable', 'string', 'max:50'],
        ]);

        // Detect duplicate photo numbers (excluding blanks) within the saved set.
        $photoNos = collect($request->assignments)
            ->pluck('photo_number')
            ->filter()
            ->map(fn($n) => trim($n));

        if ($photoNos->count() !== $photoNos->unique()->count()) {
            return back()->withErrors([
                'assignments' => 'Duplicate photo numbers detected. Each photo number must be unique within the class/section.',
            ]);
        }

        DB::transaction(function () use ($request, $schoolId) {
            foreach ($request->assignments as $row) {
                $photoNo = ($row['photo_number'] ?? '') !== '' ? trim($row['photo_number']) : null;

                Student::where('id', $row['student_id'])
                    ->where('school_id', $schoolId) // tenant guard
                    ->update(['photo_number' => $photoNo]);
            }
        });

        return back()->with('success', 'Photo numbers saved successfully.');
    }

    // ── Inline edit → queues an EditRequest, no live update ──────────────

    public function requestEdit(Request $request, Student $student)
    {
        abort_if($student->school_id !== app('current_school_id'), 403);
        abort_unless(
            auth()->user()->can('request_edit_students') || auth()->user()->can('edit_students'),
            403,
            'Unauthorized access.'
        );

        $validated = $request->validate([
            'first_name'     => 'nullable|string|max:255',
            'last_name'      => 'nullable|string|max:255',
            'address'        => 'nullable|string|max:500',
            'father_name'    => 'nullable|string|max:255',
            'mother_name'    => 'nullable|string|max:255',
            'father_phone'   => 'nullable|string|max:20',
            'mother_phone'   => 'nullable|string|max:20',
            'parent_address' => 'nullable|string|max:500',
            'reason'         => 'nullable|string|max:1000',
        ]);

        $reason = $validated['reason'] ?? null;
        unset($validated['reason']);

        $student->load('studentParent');

        $changes = [];
        $check = function ($key, $old, $new) use (&$changes) {
            if ($new === '') $new = null;
            if ($new !== null && $old != $new) $changes[$key] = $new;
        };

        // Student fields
        $check('first_name', $student->first_name, $validated['first_name'] ?? null);
        $check('last_name',  $student->last_name,  $validated['last_name']  ?? null);
        $check('address',    $student->address,    $validated['address']    ?? null);

        // Parent fields (only if parent record exists — the approval logic in
        // EditRequestController only applies parent updates when the related
        // parent is loaded, so requesting edits against a non-existent parent
        // would be a no-op on approval).
        if ($student->studentParent) {
            $check('father_name',    $student->studentParent->father_name,    $validated['father_name']    ?? null);
            $check('mother_name',    $student->studentParent->mother_name,    $validated['mother_name']    ?? null);
            $check('father_phone',   $student->studentParent->father_phone,   $validated['father_phone']   ?? null);
            $check('mother_phone',   $student->studentParent->mother_phone,   $validated['mother_phone']   ?? null);
            $check('parent_address', $student->studentParent->address,        $validated['parent_address'] ?? null);
        }

        if (empty($changes)) {
            return response()->json([
                'message' => 'No actual changes detected.',
            ], 422);
        }

        $editRequest = EditRequest::create([
            'school_id'         => app('current_school_id'),
            'user_id'           => auth()->id(),
            'requestable_type'  => Student::class,
            'requestable_id'    => $student->id,
            'requested_changes' => $changes,
            'reason'            => $reason,
            'status'            => 'pending',
        ]);

        return response()->json([
            'message'         => 'Submitted for approval.',
            'edit_request_id' => $editRequest->id,
            'pending_changes' => $changes,
        ]);
    }

    // ── Export pending student edits (xlsx | pdf) ────────────────────────

    public function exportPending(Request $request)
    {
        $schoolId  = app('current_school_id');
        $classId   = $request->integer('class_id') ?: null;
        $sectionId = $request->integer('section_id') ?: null;
        $format    = $request->query('format', 'xlsx');

        $rows = $this->buildPendingRows($schoolId, $classId, $sectionId);

        $filename = 'pending-student-edits-' . now()->format('Y-m-d_His');

        if ($format === 'pdf') {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.pending-student-edits', [
                'rows'    => $rows,
                'school'  => School::find($schoolId),
                'printed' => now()->format('d M Y, h:i A'),
                'class'   => $classId   ? CourseClass::find($classId)?->name : null,
                'section' => $sectionId ? Section::find($sectionId)?->name   : null,
                'count'   => count($rows),
            ])->setPaper('a4', 'landscape');

            return $pdf->download("{$filename}.pdf");
        }

        return Excel::download(
            new PendingStudentEditsExport($rows),
            "{$filename}.xlsx"
        );
    }

    /**
     * Load all pending Student edit requests for the school, optionally
     * filtered by class/section (via the current academic year history),
     * and flatten them into one row per (request, field) pair so each
     * change shows up on its own line in the Excel/PDF export.
     */
    private function buildPendingRows(int $schoolId, ?int $classId, ?int $sectionId): array
    {
        $currentYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $query = EditRequest::tenant()
            ->where('requestable_type', Student::class)
            ->where('status', 'pending')
            ->with([
                'user:id,name',
                'requestable:id,first_name,last_name,admission_no,school_id',
                'requestable.studentParent:id,father_name,mother_name,father_phone,mother_phone,address',
            ])
            ->latest();

        $editRequests = $query->get();

        // If a class or section filter is active, narrow to students enrolled
        // in that class/section in the current academic year. Use a separate
        // pass to keep the eager-load query above simple.
        if (($classId || $sectionId) && $currentYearId) {
            $historyQuery = StudentAcademicHistory::where('school_id', $schoolId)
                ->where('academic_year_id', $currentYearId);

            if ($classId)   $historyQuery->where('class_id', $classId);
            if ($sectionId) $historyQuery->where('section_id', $sectionId);

            $allowedStudentIds = $historyQuery->pluck('student_id')->all();
            $editRequests = $editRequests->whereIn('requestable_id', $allowedStudentIds);
        }

        // Fetch class/section labels for every visible student in one shot.
        $studentIds = $editRequests->pluck('requestable_id')->unique()->all();
        $historyByStudent = [];
        if (! empty($studentIds) && $currentYearId) {
            $histories = StudentAcademicHistory::with(['courseClass:id,name', 'section:id,name'])
                ->whereIn('student_id', $studentIds)
                ->where('academic_year_id', $currentYearId)
                ->where('school_id', $schoolId)
                ->get();
            foreach ($histories as $h) {
                $historyByStudent[$h->student_id] = [
                    'class'   => $h->courseClass?->name,
                    'section' => $h->section?->name,
                ];
            }
        }

        $rows = [];
        foreach ($editRequests as $req) {
            $student = $req->requestable;
            if (! $student) continue;

            $parent = $student->studentParent;
            $hist   = $historyByStudent[$student->id] ?? ['class' => null, 'section' => null];

            foreach (($req->requested_changes ?? []) as $field => $newValue) {
                $oldValue = match ($field) {
                    'first_name'     => $student->first_name,
                    'last_name'      => $student->last_name,
                    'address'        => $student->address,
                    'father_name'    => $parent?->father_name,
                    'mother_name'    => $parent?->mother_name,
                    'father_phone'   => $parent?->father_phone,
                    'mother_phone'   => $parent?->mother_phone,
                    'parent_address' => $parent?->address,
                    default          => $student->{$field} ?? null,
                };

                $rows[] = [
                    'admission_no'  => $student->admission_no,
                    'student_name'  => trim($student->first_name . ' ' . $student->last_name),
                    'class'         => $hist['class'] ?? '—',
                    'section'       => $hist['section'] ?? '—',
                    'field'         => self::FIELD_LABELS[$field] ?? $field,
                    'old_value'     => (string) ($oldValue ?? ''),
                    'new_value'     => (string) ($newValue ?? ''),
                    'requested_by'  => $req->user?->name ?? '—',
                    'requested_at'  => $req->created_at?->format('d M Y, h:i A'),
                    'reason'        => $req->reason ?? '',
                ];
            }
        }

        return $rows;
    }
}
