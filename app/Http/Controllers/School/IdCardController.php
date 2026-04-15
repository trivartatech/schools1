<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\CourseClass;
use App\Models\IdCardTemplate;
use App\Models\Student;
use App\Services\TeacherScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class IdCardController extends Controller
{
    // ── Template library ──────────────────────────────────────────────

    public function index()
    {
        $schoolId  = app('current_school_id');
        $templates = IdCardTemplate::where('school_id', $schoolId)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'orientation', 'background', 'columns', 'created_at']);

        return Inertia::render('School/IdCards/Index', [
            'templates' => $templates,
        ]);
    }

    // ── Designer: create ──────────────────────────────────────────────

    public function create()
    {
        return Inertia::render('School/IdCards/Designer', [
            'template' => null,
            'school'   => $this->schoolData(),
        ]);
    }

    // ── Store new template ─────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'orientation' => 'required|in:landscape,portrait',
            'background'  => 'required|array',
            'elements'    => 'required|array',
            'columns'     => 'required|integer|in:1,2,4',
        ]);

        $background = $this->resolveBackground($request->background);

        IdCardTemplate::create([
            'school_id'   => app('current_school_id'),
            'created_by'  => auth()->id(),
            'name'        => $request->name,
            'orientation' => $request->orientation,
            'background'  => $background,
            'elements'    => $request->elements,
            'columns'     => $request->columns,
        ]);

        return redirect()->route('school.utility.id-cards')
            ->with('success', 'Template "' . $request->name . '" saved.');
    }

    // ── Designer: edit ─────────────────────────────────────────────────

    public function edit(IdCardTemplate $idCardTemplate)
    {
        abort_if($idCardTemplate->school_id !== app('current_school_id'), 403);

        return Inertia::render('School/IdCards/Designer', [
            'template' => $idCardTemplate,
            'school'   => $this->schoolData(),
        ]);
    }

    // ── Update template ────────────────────────────────────────────────

    public function update(Request $request, IdCardTemplate $idCardTemplate)
    {
        abort_if($idCardTemplate->school_id !== app('current_school_id'), 403);

        $request->validate([
            'name'        => 'required|string|max:100',
            'orientation' => 'required|in:landscape,portrait',
            'background'  => 'required|array',
            'elements'    => 'required|array',
            'columns'     => 'required|integer|in:1,2,4',
        ]);

        $background = $this->resolveBackground($request->background, $idCardTemplate->background);

        $idCardTemplate->update([
            'name'        => $request->name,
            'orientation' => $request->orientation,
            'background'  => $background,
            'elements'    => $request->elements,
            'columns'     => $request->columns,
        ]);

        return redirect()->route('school.utility.id-cards')
            ->with('success', 'Template "' . $request->name . '" updated.');
    }

    // ── Delete template ────────────────────────────────────────────────

    public function destroy(IdCardTemplate $idCardTemplate)
    {
        abort_if($idCardTemplate->school_id !== app('current_school_id'), 403);

        // Delete background image if stored
        $bg = $idCardTemplate->background;
        if (($bg['type'] ?? '') === 'image' && isset($bg['value']) && str_starts_with($bg['value'], '/storage/')) {
            $path = str_replace('/storage/', '', $bg['value']);
            Storage::disk('public')->delete($path);
        }

        $idCardTemplate->delete();

        return redirect()->route('school.utility.id-cards')
            ->with('success', 'Template deleted.');
    }

    // ── Generate page (select class/section) ──────────────────────────

    public function generate(IdCardTemplate $idCardTemplate)
    {
        abort_if($idCardTemplate->school_id !== app('current_school_id'), 403);

        $schoolId = app('current_school_id');
        $classes  = CourseClass::where('school_id', $schoolId)
            ->orderBy('order_index')->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('School/IdCards/Generate', [
            'template' => $idCardTemplate,
            'classes'  => $classes,
        ]);
    }

    // ── Print page ─────────────────────────────────────────────────────

    public function print(Request $request, IdCardTemplate $idCardTemplate)
    {
        abort_if($idCardTemplate->school_id !== app('current_school_id'), 403);

        $schoolId       = app('current_school_id');
        $school         = app('current_school');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $scope = app(TeacherScopeService::class)->for(auth()->user());

        $query = Student::with([
            'currentAcademicHistory.courseClass',
            'currentAcademicHistory.section',
            'studentParent',
        ])
            ->where('school_id', $schoolId)
            ->where('status', 'active');

        if ($scope->restricted) {
            $query->whereHas('currentAcademicHistory', function ($q) use ($academicYearId, $scope) {
                $q->where('academic_year_id', $academicYearId)
                  ->where('status', 'current')
                  ->whereIn('section_id', $scope->sectionIds);
            });
        }

        if ($request->filled('class_id')) {
            $query->whereHas('currentAcademicHistory', function ($q) use ($request, $academicYearId) {
                $q->where('academic_year_id', $academicYearId)->where('class_id', $request->class_id);
            });
        }

        if ($request->filled('section_id')) {
            $query->whereHas('currentAcademicHistory', function ($q) use ($request, $academicYearId) {
                $q->where('academic_year_id', $academicYearId)->where('section_id', $request->section_id);
            });
        }

        $students = $query->orderBy('first_name')->get()->map(function ($s) {
            $h = $s->currentAcademicHistory;
            return [
                'id'           => $s->id,
                'name'         => $s->name,
                'first_name'   => $s->first_name,
                'last_name'    => $s->last_name,
                'photo_url'    => $s->photo_url,
                'admission_no' => $s->admission_no,
                'roll_no'      => $h?->roll_no ?? $s->roll_no,
                'dob'          => $s->dob,
                'blood_group'  => $s->blood_group,
                'uuid'         => $s->uuid,
                'class'        => $h?->courseClass?->name,
                'section'      => $h?->section?->name,
                'parent_phone' => $s->studentParent?->primary_phone,
                'father_name'  => $s->studentParent?->father_name,
            ];
        });

        // Allow columns override from query string
        $template = $idCardTemplate->toArray();
        if ($request->filled('columns') && in_array((int)$request->columns, [1, 2, 4])) {
            $template['columns'] = (int)$request->columns;
        }

        return Inertia::render('School/IdCards/Print', [
            'template' => $template,
            'students' => $students,
            'school'   => [
                'name'  => $school->name,
                'logo'  => $school->logo ? '/storage/' . $school->logo : null,
                'phone' => $school->phone,
                'board' => $school->board,
            ],
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function schoolData(): array
    {
        $school = app('current_school');
        return [
            'name'  => $school->name,
            'logo'  => $school->logo ? '/storage/' . $school->logo : null,
            'phone' => $school->phone,
            'board' => $school->board,
        ];
    }

    /**
     * If background value is a base64 data URI, save to storage and return file URL.
     * Otherwise return as-is (already a URL or color hex).
     */
    private function resolveBackground(array $incoming, ?array $existing = null): array
    {
        if (($incoming['type'] ?? '') === 'image' && str_starts_with($incoming['value'] ?? '', 'data:image/')) {
            // Delete old image if replacing
            if ($existing && ($existing['type'] ?? '') === 'image' && str_starts_with($existing['value'] ?? '', '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $existing['value']));
            }

            $data = $incoming['value'];
            $ext  = str_contains($data, 'image/png') ? 'png' : 'jpg';
            $path = 'id-card-backgrounds/' . uniqid('bg_', true) . '.' . $ext;
            Storage::disk('public')->put($path, base64_decode(substr($data, strpos($data, ',') + 1)));
            return ['type' => 'image', 'value' => '/storage/' . $path];
        }

        return $incoming;
    }
}
