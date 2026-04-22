<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use App\Models\CourseClass;
use App\Models\Student;
use App\Services\TeacherScopeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CertificateController extends Controller
{
    // ── Template library ──────────────────────────────────────────────

    public function index()
    {
        $schoolId  = app('current_school_id');
        $templates = CertificateTemplate::where('school_id', $schoolId)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'orientation', 'background', 'custom_vars', 'created_at']);

        return Inertia::render('School/Certificates/Index', [
            'templates' => $templates,
        ]);
    }

    // ── Designer: create ──────────────────────────────────────────────

    public function create()
    {
        return Inertia::render('School/Certificates/Designer', [
            'template' => null,
            'school'   => $this->schoolData(),
        ]);
    }

    // ── Store new template ────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'orientation' => 'required|in:landscape,portrait',
            'background'  => 'required|array',
            'elements'    => 'required|array',
            'custom_vars' => 'nullable|array',
        ]);

        $background = $this->resolveBackground($request->background);
        $elements   = $this->processElements($request->elements);

        CertificateTemplate::create([
            'school_id'   => app('current_school_id'),
            'created_by'  => auth()->id(),
            'name'        => $request->name,
            'orientation' => $request->orientation,
            'background'  => $background,
            'elements'    => $elements,
            'custom_vars' => $request->custom_vars ?? [],
        ]);

        return redirect()->route('school.utility.certificates')
            ->with('success', 'Template "' . $request->name . '" saved.');
    }

    // ── Designer: edit ────────────────────────────────────────────────

    public function edit(CertificateTemplate $certificateTemplate)
    {
        abort_if($certificateTemplate->school_id !== app('current_school_id'), 403);

        return Inertia::render('School/Certificates/Designer', [
            'template' => $certificateTemplate,
            'school'   => $this->schoolData(),
        ]);
    }

    // ── Update template ───────────────────────────────────────────────

    public function update(Request $request, CertificateTemplate $certificateTemplate)
    {
        abort_if($certificateTemplate->school_id !== app('current_school_id'), 403);

        $request->validate([
            'name'        => 'required|string|max:100',
            'orientation' => 'required|in:landscape,portrait',
            'background'  => 'required|array',
            'elements'    => 'required|array',
            'custom_vars' => 'nullable|array',
        ]);

        $background = $this->resolveBackground($request->background, $certificateTemplate->background);
        $elements   = $this->processElements($request->elements, $certificateTemplate->elements ?? []);

        $certificateTemplate->update([
            'name'        => $request->name,
            'orientation' => $request->orientation,
            'background'  => $background,
            'elements'    => $elements,
            'custom_vars' => $request->custom_vars ?? [],
        ]);

        return redirect()->route('school.utility.certificates')
            ->with('success', 'Template "' . $request->name . '" updated.');
    }

    // ── Delete template ───────────────────────────────────────────────

    public function destroy(CertificateTemplate $certificateTemplate)
    {
        abort_if($certificateTemplate->school_id !== app('current_school_id'), 403);

        // Delete background images
        $bg    = $certificateTemplate->background;
        $sides = isset($bg['front']) ? array_filter([$bg['front'] ?? null, $bg['back'] ?? null]) : [$bg];
        foreach ($sides as $side) {
            if (($side['type'] ?? '') === 'image' && str_starts_with($side['value'] ?? '', '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $side['value']));
            }
        }

        // Delete element images (signature, seal, etc.)
        foreach ($certificateTemplate->elements as $el) {
            if (($el['type'] ?? '') === 'image' && str_starts_with($el['src'] ?? '', '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $el['src']));
            }
        }

        $certificateTemplate->delete();

        return redirect()->route('school.utility.certificates')
            ->with('success', 'Template deleted.');
    }

    // ── Generate page ─────────────────────────────────────────────────

    public function generate(CertificateTemplate $certificateTemplate)
    {
        abort_if($certificateTemplate->school_id !== app('current_school_id'), 403);

        $schoolId = app('current_school_id');
        $classes  = CourseClass::where('school_id', $schoolId)
            ->orderBy('numeric_value')->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('School/Certificates/Generate', [
            'template' => $certificateTemplate,
            'classes'  => $classes,
        ]);
    }

    // ── Print page ────────────────────────────────────────────────────

    public function print(Request $request, CertificateTemplate $certificateTemplate)
    {
        abort_if($certificateTemplate->school_id !== app('current_school_id'), 403);

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

        if ($request->filled('student_id')) {
            $query->where('id', $request->student_id);
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
                'gender'       => $s->gender,
                'class'        => $h?->courseClass?->name,
                'section'      => $h?->section?->name,
                'parent_phone' => $s->studentParent?->primary_phone,
                'father_name'  => $s->studentParent?->father_name,
                'mother_name'  => $s->studentParent?->mother_name,
                'address'      => $s->address,
            ];
        });

        // Collect custom variable values from query string
        $customVals = [];
        foreach ($certificateTemplate->custom_vars ?? [] as $var) {
            $customVals[$var['key']] = $request->get($var['key'], '');
        }

        // Certificate date
        $certDate = $request->filled('cert_date')
            ? Carbon::parse($request->cert_date)->format('d F Y')
            : now()->format('d F Y');

        return Inertia::render('School/Certificates/Print', [
            'template'    => $certificateTemplate,
            'students'    => $students,
            'school'      => [
                'name'  => $school->name,
                'logo'  => $school->logo ? '/storage/' . $school->logo : null,
                'phone' => $school->phone,
                'board' => $school->board,
            ],
            'custom_vals' => $customVals,
            'cert_date'   => $certDate,
        ]);
    }

    // ── Issue a certificate (creates a verifiable record) ────────────

    public function issue(Request $request, CertificateTemplate $certificateTemplate)
    {
        abort_if($certificateTemplate->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'student_id'     => ['required', 'exists:students,id'],
            'issued_date'    => 'required|date',
            'custom_vals'    => 'nullable|array',
        ]);

        $issuance = \App\Models\CertificateIssuance::create([
            'school_id'   => app('current_school_id'),
            'template_id' => $certificateTemplate->id,
            'student_id'  => $validated['student_id'],
            'issued_date' => $validated['issued_date'],
            'custom_vals' => $validated['custom_vals'] ?? [],
            'issued_by'   => auth()->id(),
        ]);

        return response()->json([
            'token'            => $issuance->verification_token,
            'verification_url' => route('certificate.verify-public', $issuance->verification_token),
        ]);
    }

    // ── Issued certificates list ─────────────────────────────────────

    public function issued(CertificateTemplate $certificateTemplate)
    {
        abort_if($certificateTemplate->school_id !== app('current_school_id'), 403);

        $issuances = \App\Models\CertificateIssuance::where('school_id', app('current_school_id'))
            ->where('template_id', $certificateTemplate->id)
            ->with(['student', 'issuedBy'])
            ->latest()
            ->get();

        return response()->json($issuances);
    }

    // ── Helpers ───────────────────────────────────────────────────────

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
     * Save base64 background images to storage.
     * Supports both { front:{...}, back:{...} } and flat { type, value } formats.
     */
    private function resolveBackground(array $incoming, ?array $existing = null): array
    {
        if (isset($incoming['front']) || isset($incoming['back'])) {
            $result = $incoming;
            foreach (['front', 'back'] as $side) {
                if (isset($incoming[$side])) {
                    $result[$side] = $this->resolveSingleBackground($incoming[$side], $existing[$side] ?? null);
                }
            }
            return $result;
        }
        return $this->resolveSingleBackground($incoming, $existing);
    }

    private function resolveSingleBackground(array $incoming, ?array $existing = null): array
    {
        if (($incoming['type'] ?? '') === 'image' && str_starts_with($incoming['value'] ?? '', 'data:image/')) {
            if ($existing && ($existing['type'] ?? '') === 'image' && str_starts_with($existing['value'] ?? '', '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $existing['value']));
            }
            $data = $incoming['value'];
            $ext  = str_contains($data, 'image/png') ? 'png' : 'jpg';
            $path = 'certificate-backgrounds/' . uniqid('cbg_', true) . '.' . $ext;
            Storage::disk('public')->put($path, base64_decode(substr($data, strpos($data, ',') + 1)));
            return ['type' => 'image', 'value' => '/storage/' . $path];
        }
        return $incoming;
    }

    /**
     * Save any base64 image elements (signature, seal, decoration) to storage.
     */
    private function processElements(array $elements, array $existingElements = []): array
    {
        $existingMap = collect($existingElements)->keyBy('id');

        return array_map(function ($el) use ($existingMap) {
            if (($el['type'] ?? '') === 'image' && str_starts_with($el['src'] ?? '', 'data:image/')) {
                $existing = $existingMap->get($el['id']);
                if ($existing && str_starts_with($existing['src'] ?? '', '/storage/')) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $existing['src']));
                }
                $data = $el['src'];
                $ext  = str_contains($data, 'image/png') ? 'png' : 'jpg';
                $path = 'certificate-images/' . uniqid('ci_', true) . '.' . $ext;
                Storage::disk('public')->put($path, base64_decode(substr($data, strpos($data, ',') + 1)));
                $el['src'] = '/storage/' . $path;
            }
            return $el;
        }, $elements);
    }
}
