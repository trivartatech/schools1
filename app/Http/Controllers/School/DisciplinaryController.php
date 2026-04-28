<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\CourseClass;
use App\Models\DisciplinaryCategory;
use App\Models\DisciplinaryRecord;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DisciplinaryController extends Controller
{
    private const DEFAULT_CATEGORIES = [
        'Misconduct'           => 'MISC',
        'Bullying'             => 'BULLY',
        'Damage to Property'   => 'DAMG',
        'Dress Code Violation' => 'DRESS',
        'Absenteeism'          => 'ABSN',
        'Cheating'             => 'CHEAT',
        'Disrespect'           => 'DISR',
        'Violence'             => 'VIOL',
        'Other'                => 'OTH',
    ];

    private function getOrSeedCategories(int $schoolId): \Illuminate\Database\Eloquent\Collection
    {
        $cats = DisciplinaryCategory::where('school_id', $schoolId)
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        if ($cats->isEmpty()) {
            $i = 0;
            foreach (self::DEFAULT_CATEGORIES as $name => $code) {
                DisciplinaryCategory::create([
                    'school_id'  => $schoolId,
                    'name'       => $name,
                    'short_code' => $code,
                    'sort_order' => $i++,
                ]);
            }
            $cats = DisciplinaryCategory::where('school_id', $schoolId)->orderBy('sort_order')->get();
        }

        return $cats;
    }

    public function index(Request $request)
    {
        $schoolId = app('current_school_id');

        $query = DisciplinaryRecord::where('school_id', $schoolId)
            ->with(['student', 'reportedBy']);

        if ($request->filled('status'))     $query->where('status', $request->status);
        if ($request->filled('severity'))   $query->where('severity', $request->severity);
        if ($request->filled('student_id')) $query->where('student_id', $request->student_id);

        $records  = $query->latest('incident_date')->paginate(20)->withQueryString();

        $students = Student::where('school_id', $schoolId)->where('status', 'active')
            ->enrolledInCurrentYear()
            ->with(['currentAcademicHistory' => fn($q) => $q->select('id', 'student_id', 'class_id', 'section_id', 'roll_no', 'academic_year_id')])
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'admission_no']);

        $classes    = CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name')->get(['id', 'name']);
        $sections   = Section::where('school_id', $schoolId)->forCurrentYear()->orderBy('name')->get(['id', 'course_class_id', 'name']);
        $categories = $this->getOrSeedCategories($schoolId);

        $summary = [
            'total'      => DisciplinaryRecord::where('school_id', $schoolId)->count(),
            'open'       => DisciplinaryRecord::where('school_id', $schoolId)->where('status', 'open')->count(),
            'this_month' => DisciplinaryRecord::where('school_id', $schoolId)->whereMonth('incident_date', now()->month)->whereYear('incident_date', now()->year)->count(),
            'major'      => DisciplinaryRecord::where('school_id', $schoolId)->where('severity', 'major')->count(),
        ];

        return Inertia::render('School/Disciplinary/Index', [
            'records'    => $records,
            'students'   => $students,
            'classes'    => $classes,
            'sections'   => $sections,
            'categories' => $categories,
            'summary'    => $summary,
            'filters'    => $request->only('status', 'severity', 'student_id'),
        ]);
    }

    public function store(Request $request)
    {
        $schoolId  = app('current_school_id');
        $validated = $request->validate([
            'student_id'       => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'incident_date'    => 'required|date',
            'category'         => 'required|string|max:100',
            'severity'         => 'required|in:minor,moderate,major',
            'description'      => 'required|string',
            'action_taken'     => 'nullable|string',
            'consequence'      => 'nullable|in:warning,detention,parent_call,suspension,expulsion,none',
            'consequence_from' => 'nullable|date',
            'consequence_to'   => 'nullable|date|after_or_equal:consequence_from',
            'student_statement'=> 'nullable|string',
            'notes'            => 'nullable|string',
        ]);

        DisciplinaryRecord::create(array_merge($validated, [
            'school_id'   => $schoolId,
            'reported_by' => auth()->id(),
            'status'      => 'open',
        ]));

        return back()->with('success', 'Disciplinary record created.');
    }

    public function storeBulk(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'assignments'                => 'required|array|min:1',
            'assignments.*.student_id'   => ['required', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'assignments.*.category'     => 'required|string|max:100',
            'assignments.*.severity'     => 'required|in:minor,moderate,major',
            'assignments.*.consequence'  => 'nullable|in:warning,detention,parent_call,suspension,expulsion,none,',
            'incident_date'              => 'required|date',
            'action_taken'               => 'nullable|string',
        ]);

        $now  = now();
        $base = [
            'school_id'     => $schoolId,
            'reported_by'   => auth()->id(),
            'status'        => 'open',
            'incident_date' => $validated['incident_date'],
            'action_taken'  => $validated['action_taken'] ?? null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];

        $rows = array_map(
            fn ($a) => $base + [
                'student_id'  => $a['student_id'],
                'category'    => $a['category'],
                'severity'    => $a['severity'],
                'consequence' => !empty($a['consequence']) ? $a['consequence'] : null,
                'description' => $a['category'],
            ],
            $validated['assignments']
        );

        DisciplinaryRecord::insert($rows);

        $count = count($rows);
        return back()->with('success', "$count disciplinary record" . ($count === 1 ? '' : 's') . ' created.');
    }

    public function update(Request $request, DisciplinaryRecord $disciplinaryRecord)
    {
        abort_if($disciplinaryRecord->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'incident_date'    => 'required|date',
            'category'         => 'required|string|max:100',
            'severity'         => 'required|in:minor,moderate,major',
            'description'      => 'required|string',
            'action_taken'     => 'nullable|string',
            'status'           => 'required|in:open,under_review,resolved,escalated',
            'consequence'      => 'nullable|in:warning,detention,parent_call,suspension,expulsion,none',
            'consequence_from' => 'nullable|date',
            'consequence_to'   => 'nullable|date|after_or_equal:consequence_from',
            'parent_notified'  => 'boolean',
            'student_statement'=> 'nullable|string',
            'notes'            => 'nullable|string',
        ]);

        if ($validated['parent_notified'] && !$disciplinaryRecord->parent_notified) {
            $validated['parent_notified_at'] = now()->toDateString();
        }

        if (in_array($validated['status'], ['resolved', 'escalated'])) {
            $validated['reviewed_by'] = auth()->id();
        }

        $disciplinaryRecord->update($validated);

        return back()->with('success', 'Record updated.');
    }

    public function destroy(DisciplinaryRecord $disciplinaryRecord)
    {
        abort_if($disciplinaryRecord->school_id !== app('current_school_id'), 403);
        $disciplinaryRecord->delete();
        return back()->with('success', 'Record deleted.');
    }

    // ── Category management ─────────────────────────────────────────

    public function storeCategory(Request $request)
    {
        $schoolId = app('current_school_id');
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'short_code' => [
                'required', 'string', 'max:20',
                Rule::unique('disciplinary_categories', 'short_code')
                    ->where('school_id', $schoolId),
            ],
        ]);

        $max = DisciplinaryCategory::where('school_id', $schoolId)->max('sort_order') ?? -1;
        DisciplinaryCategory::create([
            'school_id'  => $schoolId,
            'name'       => trim($data['name']),
            'short_code' => strtoupper(trim($data['short_code'])),
            'sort_order' => $max + 1,
        ]);

        return back()->with('success', 'Category added.');
    }

    public function updateCategory(Request $request, DisciplinaryCategory $disciplinaryCategory)
    {
        abort_if($disciplinaryCategory->school_id !== app('current_school_id'), 403);
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'short_code' => [
                'required', 'string', 'max:20',
                Rule::unique('disciplinary_categories', 'short_code')
                    ->where('school_id', $disciplinaryCategory->school_id)
                    ->ignore($disciplinaryCategory->id),
            ],
        ]);
        $disciplinaryCategory->update([
            'name'       => trim($data['name']),
            'short_code' => strtoupper(trim($data['short_code'])),
        ]);
        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(DisciplinaryCategory $disciplinaryCategory)
    {
        abort_if($disciplinaryCategory->school_id !== app('current_school_id'), 403);
        $disciplinaryCategory->delete();
        return back()->with('success', 'Category deleted.');
    }

    public function studentHistory(Request $request, Student $student)
    {
        abort_if($student->school_id !== app('current_school_id'), 403);

        $records = DisciplinaryRecord::where('school_id', app('current_school_id'))
            ->where('student_id', $student->id)
            ->with('reportedBy', 'reviewedBy')
            ->latest('incident_date')
            ->get();

        return response()->json($records);
    }
}
