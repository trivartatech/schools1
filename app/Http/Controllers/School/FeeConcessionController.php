<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FeeConcession;
use App\Models\FeeHead;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FeeConcessionController extends Controller
{
    private function schoolId(): int
    {
        return app('current_school_id');
    }

    private function academicYearId(): ?int
    {
        return app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
    }

    // ── List all concessions ─────────────────────────────────────────────────
    public function index(Request $request)
    {
        $schoolId       = $this->schoolId();
        $academicYearId = $this->academicYearId();

        $query = FeeConcession::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->with(['student', 'createdBy'])
            ->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('student', fn($s) => $s->where('first_name', 'like', "%{$search}%")
                                                       ->orWhere('last_name', 'like', "%{$search}%")
                                                       ->orWhere('admission_no', 'like', "%{$search}%"));
            });
        }

        $concessions = $query->paginate(20)->withQueryString();

        $feeHeads = FeeHead::where('school_id', $schoolId)->get(['id', 'name']);
        
        $concessionTypes = \App\Models\FeeConcessionType::where('school_id', $schoolId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return Inertia::render('School/Fee/Concessions/Index', [
            'concessions'     => $concessions,
            'feeHeads'        => $feeHeads,
            'concessionTypes' => $concessionTypes,
            'filters'         => $request->only(['search']),
        ]);
    }

    // ── Store new concession ─────────────────────────────────────────────────
    public function store(Request $request)
    {
        $schoolId       = $this->schoolId();
        $academicYearId = $this->academicYearId();

        $validated = $request->validate([
            'student_id'            => 'required|exists:students,id',
            'fee_type'              => ['required', Rule::in(FeeConcession::FEE_TYPES)],
            'name'                  => [
                'required', 'string', 'max:100',
                Rule::unique('fee_concessions')->where(fn($q) => $q
                    ->where('school_id', $schoolId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('student_id', $request->student_id)
                    ->where('fee_type', $request->fee_type)
                ),
            ],
            'description'           => 'nullable|string|max:500',
            'type'                  => 'required|in:percentage,fixed',
            'value'                 => 'required|numeric|min:0.01',
            'is_active'             => 'boolean',
            'is_one_time'           => 'boolean',
        ]);

        if ($validated['type'] === 'percentage' && $validated['value'] > 100) {
            return back()->withErrors(['value' => 'Percentage cannot exceed 100%.']);
        }

        FeeConcession::create([
            ...$validated,
            'school_id'        => $schoolId,
            'academic_year_id' => $academicYearId,
            'created_by'       => auth()->id(),
        ]);

        return back()->with('success', 'Concession added successfully.');
    }

    // ── Update existing concession ───────────────────────────────────────────
    public function update(Request $request, FeeConcession $feeConcession)
    {
        $schoolId       = $this->schoolId();
        $academicYearId = $this->academicYearId();
        
        if ($feeConcession->school_id !== $schoolId) {
            abort(403, 'Unauthorized access to this concession record');
        }

        $validated = $request->validate([
            'fee_type'              => ['required', Rule::in(FeeConcession::FEE_TYPES)],
            'name'                  => [
                'required', 'string', 'max:100',
                Rule::unique('fee_concessions')->where(fn($q) => $q
                    ->where('school_id', $schoolId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('student_id', $feeConcession->student_id)
                    ->where('fee_type', $request->fee_type)
                )->ignore($feeConcession->id),
            ],
            'description'           => 'nullable|string|max:500',
            'type'                  => 'required|in:percentage,fixed',
            'value'                 => 'required|numeric|min:0.01',
            'is_active'             => 'boolean',
            'is_one_time'           => 'boolean',
        ]);

        $feeConcession->update($validated);

        return back()->with('success', 'Concession updated.');
    }

    // ── Toggle active status ─────────────────────────────────────────────────
    public function toggleActive(FeeConcession $feeConcession)
    {
        if ($feeConcession->school_id !== $this->schoolId()) {
            abort(403);
        }

        $feeConcession->update(['is_active' => !$feeConcession->is_active]);
        return back()->with('success', $feeConcession->is_active ? 'Concession activated.' : 'Concession deactivated.');
    }

    // ── Delete ───────────────────────────────────────────────────────────────
    public function destroy(FeeConcession $feeConcession)
    {
        if ($feeConcession->school_id !== $this->schoolId()) {
            abort(403);
        }

        $feeConcession->delete();
        return back()->with('success', 'Concession deleted.');
    }

    /**
     * API: Get active concessions for a student.
     *
     * Used by every collection screen — Tuition's Collect.vue, plus the
     * Transport / Hostel / Stationary collect pages. Filtered by
     * ?fee_type= so each collection screen sees only the concessions
     * scoped to its own stream.
     */
    public function forStudent(Request $request, Student $student)
    {
        $schoolId       = $this->schoolId();
        $academicYearId = $this->academicYearId();

        $feeType = strtolower((string) $request->query('fee_type', 'tuition'));
        if (! in_array($feeType, FeeConcession::FEE_TYPES, true)) {
            $feeType = 'tuition';
        }

        $concessions = FeeConcession::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('student_id', $student->id)
            ->where('fee_type', $feeType)
            ->where('is_active', true)
            ->withCount(['payments', 'transportPayments', 'hostelPayments', 'stationaryPayments'])
            ->get(['id', 'fee_type', 'name', 'description', 'type', 'value', 'is_one_time'])
            ->filter(function ($c) {
                // Hide if already applied on any stream — concessions are single-use
                $used = ($c->payments_count             ?? 0)
                      + ($c->transport_payments_count   ?? 0)
                      + ($c->hostel_payments_count      ?? 0)
                      + ($c->stationary_payments_count  ?? 0);
                return $used === 0;
            })
            ->values();

        return response()->json($concessions);
    }
}
