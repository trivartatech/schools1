<?php

namespace App\Http\Controllers\School\Stationary;

use App\Http\Controllers\Controller;
use App\Models\StationaryAllocationItem;
use App\Models\StationaryItem;
use App\Models\StationaryStudentAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AllocationController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        $search   = trim((string) $request->input('q', ''));
        $status   = $request->input('payment_status', '');

        $query = StationaryStudentAllocation::tenant()
            ->with([
                'student:id,admission_no,first_name,last_name,user_id',
                'student.user:id,name',
                'academicYear:id,name',
            ])
            ->orderByDesc('created_at');

        if ($search !== '') {
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('admission_no', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name',  'like', "%{$search}%");
            });
        }

        if (in_array($status, ['unpaid', 'partial', 'paid', 'waived'], true)) {
            $query->where('payment_status', $status);
        }

        $allocations = $query->paginate(20)->withQueryString();

        $items   = StationaryItem::tenant()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code', 'unit_price', 'current_stock']);
        $classes = \App\Models\CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('School/Stationary/Allocations/Index', [
            'allocations' => $allocations,
            'items'       => $items,
            'classes'     => $classes,
            'filters'     => ['q' => $search, 'payment_status' => $status],
        ]);
    }

    public function show(StationaryStudentAllocation $allocation)
    {
        $this->authorizeTenant($allocation);

        $allocation->load([
            'student:id,admission_no,first_name,last_name,user_id',
            'student.user:id,name,email,phone',
            'academicYear:id,name',
            'lineItems' => fn ($q) => $q->with('item:id,name,code,unit_price,current_stock'),
            'payments'  => fn ($q) => $q->with('collectedBy:id,name')->orderByDesc('payment_date'),
            'issuances' => fn ($q) => $q->with(['issuedBy:id,name', 'items.item:id,name,code'])->orderByDesc('issued_at'),
            'returns'   => fn ($q) => $q->with(['acceptedBy:id,name', 'items.item:id,name,code'])->orderByDesc('returned_at'),
        ]);

        return Inertia::render('School/Stationary/Allocations/Show', [
            'allocation' => $allocation,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');

        $validated = $request->validate([
            'student_ids'      => 'required|array|min:1',
            'student_ids.*'    => [Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'lines'            => 'required|array|min:1',
            'lines.*.item_id'  => ['required', Rule::exists('stationary_items', 'id')->where('school_id', $schoolId)],
            'lines.*.qty'      => 'required|integer|min:1',
            'remarks'          => 'nullable|string|max:2000',
            'status'           => 'in:active,inactive',
        ]);

        $itemIds = collect($validated['lines'])->pluck('item_id')->all();
        $itemMap = StationaryItem::tenant()->whereIn('id', $itemIds)->get()->keyBy('id');

        DB::transaction(function () use ($validated, $itemMap, $schoolId, $academicYearId) {
            foreach ($validated['student_ids'] as $studentId) {
                // Block double-allocation per academic year
                $existing = StationaryStudentAllocation::tenant()
                    ->where('student_id', $studentId)
                    ->where('academic_year_id', $academicYearId)
                    ->first();

                if ($existing) continue;

                $totalAmount = 0;
                $linesData   = [];
                foreach ($validated['lines'] as $line) {
                    $item       = $itemMap[$line['item_id']];
                    $unitPrice  = (float) $item->unit_price;
                    $qty        = (int) $line['qty'];
                    $lineTotal  = round($unitPrice * $qty, 2);
                    $totalAmount += $lineTotal;
                    $linesData[]  = [
                        'item_id'       => $item->id,
                        'qty_entitled'  => $qty,
                        'qty_collected' => 0,
                        'unit_price'    => $unitPrice,
                        'line_total'    => $lineTotal,
                    ];
                }

                $allocation = StationaryStudentAllocation::create([
                    'school_id'         => $schoolId,
                    'student_id'        => $studentId,
                    'academic_year_id'  => $academicYearId,
                    'total_amount'      => $totalAmount,
                    'amount_paid'       => 0,
                    'discount'          => 0,
                    'fine'              => 0,
                    'balance'           => $totalAmount,
                    'payment_status'    => $totalAmount > 0 ? 'unpaid' : 'paid',
                    'collection_status' => 'none',
                    'status'            => $validated['status'] ?? 'active',
                    'remarks'           => $validated['remarks'] ?? null,
                ]);

                foreach ($linesData as $row) {
                    $allocation->lineItems()->create($row);
                }
            }
        });

        return back()->with('success', 'Stationary kit allocated.');
    }

    public function update(Request $request, StationaryStudentAllocation $allocation)
    {
        $this->authorizeTenant($allocation);
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'lines'                => 'required|array|min:1',
            'lines.*.id'           => 'nullable|integer',
            'lines.*.item_id'      => ['required', Rule::exists('stationary_items', 'id')->where('school_id', $schoolId)],
            'lines.*.qty_entitled' => 'required|integer|min:0',
            'remarks'              => 'nullable|string|max:2000',
            'status'               => 'in:active,inactive',
        ]);

        $itemMap = StationaryItem::tenant()
            ->whereIn('id', collect($validated['lines'])->pluck('item_id')->all())
            ->get()->keyBy('id');

        DB::transaction(function () use ($allocation, $validated, $itemMap) {
            $existingLines = $allocation->lineItems()->get()->keyBy('id');
            $keptIds       = [];

            foreach ($validated['lines'] as $row) {
                $itemId   = (int) $row['item_id'];
                $newQty   = (int) $row['qty_entitled'];
                $unitPrice = (float) $itemMap[$itemId]->unit_price;
                $lineTotal = round($unitPrice * $newQty, 2);

                if (! empty($row['id']) && isset($existingLines[$row['id']])) {
                    $line = $existingLines[$row['id']];
                    abort_if(
                        $newQty < $line->qty_collected,
                        422,
                        "Cannot reduce qty_entitled below qty_collected ({$line->qty_collected}) on item #{$itemId}."
                    );
                    $line->update([
                        'item_id'      => $itemId,
                        'qty_entitled' => $newQty,
                        'unit_price'   => $unitPrice,
                        'line_total'   => $lineTotal,
                    ]);
                    $keptIds[] = $line->id;
                } else {
                    $newLine = $allocation->lineItems()->create([
                        'item_id'       => $itemId,
                        'qty_entitled'  => $newQty,
                        'qty_collected' => 0,
                        'unit_price'    => $unitPrice,
                        'line_total'    => $lineTotal,
                    ]);
                    $keptIds[] = $newLine->id;
                }
            }

            // Lines removed from the form: delete only if untouched
            $toRemove = $existingLines->keys()->diff($keptIds);
            foreach ($toRemove as $id) {
                $line = $existingLines[$id];
                if ($line->qty_collected > 0 || $line->returnLines()->exists()) {
                    abort(422, "Cannot remove line #{$id} — items have already been issued or returned against it.");
                }
                $line->delete();
            }

            $allocation->update([
                'remarks' => $validated['remarks'] ?? $allocation->remarks,
                'status'  => $validated['status']  ?? $allocation->status,
            ]);
        });

        $allocation->refresh()->recalculateTotals();
        $allocation->refresh()->recalculateCollectionStatus();

        return back()->with('success', 'Allocation updated.');
    }

    public function destroy(StationaryStudentAllocation $allocation)
    {
        $this->authorizeTenant($allocation);

        if ($allocation->payments()->exists()) {
            return back()->withErrors([
                'allocation' => 'Cannot delete — receipts exist. Void receipts first or mark allocation inactive.',
            ]);
        }
        if ($allocation->issuances()->exists()) {
            return back()->withErrors([
                'allocation' => 'Cannot delete — issuance log exists. Void issuances first or mark allocation inactive.',
            ]);
        }

        $allocation->delete();

        return back()->with('success', 'Allocation removed.');
    }

    public function studentsByClass(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app('current_academic_year_id');
        $classId        = $request->get('class_id');
        $sectionId      = $request->get('section_id');

        $query = \App\Models\StudentAcademicHistory::with('student:id,first_name,last_name,admission_no')
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->where('status', 'current');

        if ($sectionId) {
            $query->where('section_id', $sectionId);
        }

        // Exclude students who already have a stationary allocation in current year
        $existingStudentIds = StationaryStudentAllocation::tenant()
            ->where('academic_year_id', $academicYearId)
            ->pluck('student_id')
            ->all();

        $students = $query->get()
            ->filter(fn ($h) => $h->student !== null && ! in_array($h->student_id, $existingStudentIds, true))
            ->map(fn ($h) => [
                'id'           => $h->student_id,
                'name'         => trim(($h->student->first_name ?? '') . ' ' . ($h->student->last_name ?? '')),
                'admission_no' => $h->student->admission_no,
            ])
            ->values();

        return response()->json($students);
    }

    private function authorizeTenant(StationaryStudentAllocation $allocation): void
    {
        abort_unless($allocation->school_id === app('current_school_id'), 403);
    }
}
