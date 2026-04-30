<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\StationaryAllocationItem;
use App\Models\StationaryFeePayment;
use App\Models\StationaryIssuance;
use App\Models\StationaryItem;
use App\Models\StationaryReturn;
use App\Models\StationaryStudentAllocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Mobile Stationary Items endpoints.
 *
 * Mirrors App\Http\Controllers\School\Stationary\ItemController. Same
 * `stationary_items` table, same validation rules. Allocations / fees /
 * issuance / returns stay on web for now — mobile v1 covers the catalog
 * (the most-edited surface) so an admin can add a new item to the
 * stationary register from anywhere.
 */
class StationaryController extends Controller
{
    private function assertStationaryAdmin(Request $request): void
    {
        $user = $request->user();
        $type = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        if (!in_array($type, ['admin', 'school_admin', 'principal', 'super_admin'], true)) {
            abort(response()->json(['error' => 'Unauthorized.'], 403));
        }
    }

    private function shapeItem(StationaryItem $i): array
    {
        return [
            'id'            => $i->id,
            'name'          => $i->name,
            'code'          => $i->code,
            'hsn_code'      => $i->hsn_code,
            'unit_price'    => (float) $i->unit_price,
            'current_stock' => (int) $i->current_stock,
            'min_stock'     => (int) $i->min_stock,
            'is_low_stock'  => (int) $i->current_stock <= (int) $i->min_stock,
            'status'        => $i->status,
            'description'   => $i->description,
            'created_at'    => $i->created_at?->toIso8601String(),
        ];
    }

    /**
     * GET /mobile/stationary/items
     *
     * Query params (optional):
     *   q              substring on name or code
     *   status         active | inactive
     *   low_stock_only '1' to filter to current_stock <= min_stock (active only)
     *   per_page       5..100 (default 30)
     *   page           >=1
     */
    public function items(Request $request): JsonResponse
    {
        $this->assertStationaryAdmin($request);
        $schoolId = app('current_school_id');

        $query = StationaryItem::where('school_id', $schoolId);

        if ($q = trim((string) $request->input('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('code', 'like', "%{$q}%");
            });
        }
        if (in_array($request->input('status'), ['active', 'inactive'], true)) {
            $query->where('status', $request->input('status'));
        }
        if ($request->boolean('low_stock_only')) {
            $query->whereColumn('current_stock', '<=', 'min_stock')
                  ->where('status', 'active');
        }

        $perPage = max(5, min(100, (int) $request->input('per_page', 30)));
        $paginated = $query->orderBy('name')->paginate($perPage);

        // Summary stats over the full school catalog (not just the page) so
        // admins always see the real counts at the top of the screen.
        $stats = [
            'total'     => StationaryItem::where('school_id', $schoolId)->count(),
            'active'    => StationaryItem::where('school_id', $schoolId)->where('status', 'active')->count(),
            'low_stock' => StationaryItem::where('school_id', $schoolId)
                ->whereColumn('current_stock', '<=', 'min_stock')
                ->where('status', 'active')
                ->count(),
        ];

        return response()->json([
            'data'         => collect($paginated->items())->map(fn($i) => $this->shapeItem($i))->values(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'summary'      => $stats,
        ]);
    }

    /** POST /mobile/stationary/items — create */
    public function storeItem(Request $request): JsonResponse
    {
        $this->assertStationaryAdmin($request);
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'code'          => [
                'nullable', 'string', 'max:50',
                Rule::unique('stationary_items', 'code')
                    ->where(fn($q) => $q->where('school_id', $schoolId)),
            ],
            'unit_price'    => 'required|numeric|min:0',
            'hsn_code'      => 'nullable|string|max:30',
            'current_stock' => 'required|integer|min:0',
            'min_stock'     => 'required|integer|min:0',
            'status'        => 'required|in:active,inactive',
            'description'   => 'nullable|string|max:2000',
        ]);

        $item = StationaryItem::create(array_merge($validated, ['school_id' => $schoolId]));

        return response()->json([
            'message' => 'Stationary item created.',
            'data'    => $this->shapeItem($item),
        ], 201);
    }

    /** PATCH /mobile/stationary/items/{id} — update */
    public function updateItem(Request $request, int $id): JsonResponse
    {
        $this->assertStationaryAdmin($request);
        $schoolId = app('current_school_id');

        $item = StationaryItem::where('school_id', $schoolId)->find($id);
        if (!$item) {
            return response()->json(['error' => 'Item not found.'], 404);
        }

        $validated = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'code'          => [
                'nullable', 'string', 'max:50',
                Rule::unique('stationary_items', 'code')
                    ->ignore($item->id)
                    ->where(fn($q) => $q->where('school_id', $schoolId)),
            ],
            'unit_price'    => 'sometimes|required|numeric|min:0',
            'hsn_code'      => 'nullable|string|max:30',
            'current_stock' => 'sometimes|required|integer|min:0',
            'min_stock'     => 'sometimes|required|integer|min:0',
            'status'        => 'sometimes|required|in:active,inactive',
            'description'   => 'nullable|string|max:2000',
        ]);

        $item->update($validated);
        return response()->json([
            'message' => 'Stationary item updated.',
            'data'    => $this->shapeItem($item->fresh()),
        ]);
    }

    /**
     * DELETE /mobile/stationary/items/{id}
     *
     * Refuses to delete items already referenced by allocations — same
     * guard as the web ItemController::destroy(). The mobile UI surfaces
     * the returned message so admins know to mark the item inactive
     * instead of deleting.
     */
    public function destroyItem(Request $request, int $id): JsonResponse
    {
        $this->assertStationaryAdmin($request);
        $schoolId = app('current_school_id');

        $item = StationaryItem::where('school_id', $schoolId)->find($id);
        if (!$item) {
            return response()->json(['error' => 'Item not found.'], 404);
        }

        if ($item->allocationItems()->exists()) {
            return response()->json([
                'error' => 'This item is already issued to one or more students and cannot be deleted. Mark it inactive instead.',
            ], 409);
        }

        $item->delete();
        return response()->json(['message' => 'Item deleted.', 'id' => $id]);
    }

    // ── Allocations ────────────────────────────────────────────────────────────

    private function allocationPayload(StationaryStudentAllocation $a): array
    {
        $a->loadMissing(['student:id,first_name,last_name,admission_no,school_id', 'lineItems.item:id,name,code,unit_price']);

        return [
            'id'                => $a->id,
            'student'           => $a->student ? [
                'id'           => $a->student->id,
                'name'         => trim(($a->student->first_name ?? '') . ' ' . ($a->student->last_name ?? '')),
                'admission_no' => $a->student->admission_no,
            ] : null,
            'total_amount'      => (float) $a->total_amount,
            'amount_paid'       => (float) $a->amount_paid,
            'discount'          => (float) $a->discount,
            'fine'              => (float) $a->fine,
            'balance'           => (float) $a->balance,
            'payment_status'    => $a->payment_status,
            'collection_status' => $a->collection_status,
            'status'            => $a->status,
            'remarks'           => $a->remarks,
            'last_payment_date' => $a->last_payment_date?->toDateString(),
            'lines'             => $a->lineItems->map(fn ($l) => [
                'id'             => $l->id,
                'item_id'        => $l->item_id,
                'item_name'      => $l->item?->name ?? 'Unknown',
                'item_code'      => $l->item?->code,
                'qty_entitled'   => (int) $l->qty_entitled,
                'qty_collected'  => (int) $l->qty_collected,
                'unit_price'     => (float) $l->unit_price,
                'line_total'     => (float) $l->line_total,
            ])->values(),
        ];
    }

    /**
     * GET /mobile/stationary/allocations
     * Query params: q (student search), status (paid|unpaid|partial|all), per_page.
     */
    public function allocations(Request $request): JsonResponse
    {
        $this->assertStationaryAdmin($request);
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $q       = trim((string) $request->input('q', ''));
        $status  = (string) $request->input('status', 'all');
        $perPage = max(5, min(100, (int) $request->input('per_page', 30)));

        $query = StationaryStudentAllocation::where('school_id', $schoolId)
            ->when($academicYearId, fn ($qq) => $qq->where('academic_year_id', $academicYearId))
            ->with(['student:id,first_name,last_name,admission_no,school_id', 'lineItems.item:id,name,code,unit_price'])
            ->latest();

        if (in_array($status, ['paid', 'unpaid', 'partial', 'waived'], true)) {
            $query->where('payment_status', $status);
        }

        if ($q !== '') {
            $query->whereHas('student', function ($s) use ($q) {
                $s->where('first_name', 'like', "%{$q}%")
                  ->orWhere('last_name', 'like', "%{$q}%")
                  ->orWhere('admission_no', 'like', "%{$q}%");
            });
        }

        $paginated = $query->paginate($perPage);

        // Summary across the year (not just the page)
        $summary = StationaryStudentAllocation::where('school_id', $schoolId)
            ->when($academicYearId, fn ($qq) => $qq->where('academic_year_id', $academicYearId))
            ->selectRaw('COUNT(*) as cnt, SUM(total_amount) as total, SUM(amount_paid) as paid, SUM(balance) as outstanding,
                         SUM(CASE WHEN payment_status=\'paid\' THEN 1 ELSE 0 END) as paid_count,
                         SUM(CASE WHEN payment_status=\'unpaid\' THEN 1 ELSE 0 END) as unpaid_count')
            ->first();

        return response()->json([
            'data'         => collect($paginated->items())->map(fn ($a) => $this->allocationPayload($a))->values(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
            'summary'      => [
                'allocations' => (int) ($summary->cnt          ?? 0),
                'total'       => (float) ($summary->total      ?? 0),
                'paid'        => (float) ($summary->paid       ?? 0),
                'outstanding' => (float) ($summary->outstanding ?? 0),
                'paid_count'  => (int)   ($summary->paid_count  ?? 0),
                'unpaid_count'=> (int)   ($summary->unpaid_count?? 0),
            ],
        ]);
    }

    /**
     * POST /mobile/stationary/allocations
     * Body: { student_ids: [int], lines: [{item_id, qty}], remarks? }
     * Skips students who already have an allocation for the current year (matches web).
     */
    public function createAllocation(Request $request): JsonResponse
    {
        $this->assertStationaryAdmin($request);
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $validated = $request->validate([
            'student_ids'      => 'required|array|min:1',
            'student_ids.*'    => [Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'lines'            => 'required|array|min:1',
            'lines.*.item_id'  => ['required', Rule::exists('stationary_items', 'id')->where('school_id', $schoolId)],
            'lines.*.qty'      => 'required|integer|min:1',
            'remarks'          => 'nullable|string|max:2000',
        ]);

        $itemIds = collect($validated['lines'])->pluck('item_id')->all();
        $itemMap = StationaryItem::where('school_id', $schoolId)
            ->whereIn('id', $itemIds)->get()->keyBy('id');

        $created = 0;
        $skipped = 0;
        DB::transaction(function () use ($validated, $itemMap, $schoolId, $academicYearId, &$created, &$skipped) {
            foreach ($validated['student_ids'] as $studentId) {
                $existing = StationaryStudentAllocation::where('school_id', $schoolId)
                    ->where('student_id', $studentId)
                    ->where('academic_year_id', $academicYearId)
                    ->first();
                if ($existing) { $skipped++; continue; }

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
                    'status'            => 'active',
                    'remarks'           => $validated['remarks'] ?? null,
                ]);
                foreach ($linesData as $row) {
                    $allocation->lineItems()->create($row);
                }
                $created++;
            }
        });

        return response()->json([
            'message' => "Allocated to {$created} student(s)" . ($skipped > 0 ? ", skipped {$skipped} (already allocated this year)" : '') . '.',
            'created' => $created,
            'skipped' => $skipped,
        ], 201);
    }

    // ── Fee collection ────────────────────────────────────────────────────────

    /**
     * POST /mobile/stationary/allocations/{id}/collect
     */
    public function collect(Request $request, int $id): JsonResponse
    {
        $this->assertStationaryAdmin($request);
        $schoolId = app('current_school_id');

        $allocation = StationaryStudentAllocation::where('school_id', $schoolId)->find($id);
        if (!$allocation) {
            return response()->json(['error' => 'Allocation not found.'], 404);
        }

        $validated = $request->validate([
            'amount_paid'     => 'required|numeric|min:0.01',
            'discount'        => 'nullable|numeric|min:0',
            'fine'            => 'nullable|numeric|min:0',
            'payment_date'    => 'required|date',
            'payment_mode'    => 'required|string|max:50',
            'transaction_ref' => 'nullable|string|max:100',
            'remarks'         => 'nullable|string|max:500',
        ]);

        $discount = (float) ($validated['discount'] ?? 0);
        $fine     = (float) ($validated['fine']     ?? 0);

        if ((float) $validated['amount_paid'] + $discount > (float) $allocation->balance + $fine + 0.01) {
            return response()->json([
                'error' => 'Payment + discount exceeds outstanding balance (' . number_format($allocation->balance, 2) . ').',
            ], 422);
        }

        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : $allocation->academic_year_id;

        $payment = null;
        DB::transaction(function () use (&$payment, $allocation, $validated, $discount, $fine, $academicYearId) {
            $payment = StationaryFeePayment::create([
                'school_id'        => $allocation->school_id,
                'allocation_id'    => $allocation->id,
                'student_id'       => $allocation->student_id,
                'academic_year_id' => $academicYearId,
                'amount_paid'      => $validated['amount_paid'],
                'discount'         => $discount,
                'fine'             => $fine,
                'payment_date'     => $validated['payment_date'],
                'payment_mode'     => $validated['payment_mode'],
                'transaction_ref'  => $validated['transaction_ref'] ?? null,
                'remarks'          => $validated['remarks'] ?? null,
                'collected_by'     => auth()->id(),
            ]);
            $allocation->refresh()->recalculateTotals();
        });

        $allocation->refresh();

        return response()->json([
            'message' => 'Payment recorded.',
            'payment_id' => $payment?->id,
            'data'    => $this->allocationPayload($allocation),
        ], 201);
    }

    // ── Issuance ──────────────────────────────────────────────────────────────

    private function issuancePayload(StationaryIssuance $iss): array
    {
        $iss->loadMissing(['issuedBy:id,name', 'items.item:id,name,code']);
        return [
            'id'         => $iss->id,
            'issued_at'  => $iss->issued_at?->toIso8601String(),
            'issued_by'  => $iss->issuedBy ? ['id' => $iss->issuedBy->id, 'name' => $iss->issuedBy->name] : null,
            'remarks'    => $iss->remarks,
            'lines'      => $iss->items->map(fn ($l) => [
                'id'            => $l->id,
                'item_id'       => $l->item_id,
                'item_name'     => $l->item?->name ?? 'Unknown',
                'item_code'     => $l->item?->code,
                'qty_issued'    => (int) $l->qty_issued,
            ])->values(),
        ];
    }

    /**
     * GET /mobile/stationary/allocations/{id}/issuances
     */
    public function issuances(Request $request, int $id): JsonResponse
    {
        $this->assertStationaryAdmin($request);
        $schoolId = app('current_school_id');

        $allocation = StationaryStudentAllocation::where('school_id', $schoolId)->find($id);
        if (!$allocation) return response()->json(['error' => 'Allocation not found.'], 404);

        $list = $allocation->issuances()
            ->with(['issuedBy:id,name', 'items.item:id,name,code'])
            ->orderByDesc('issued_at')
            ->get();

        return response()->json([
            'data' => $list->map(fn ($i) => $this->issuancePayload($i))->values(),
        ]);
    }

    /**
     * POST /mobile/stationary/allocations/{id}/issuances
     * Body: { lines: [{ allocation_item_id, qty_issued }], remarks? }
     */
    public function createIssuance(Request $request, int $id): JsonResponse
    {
        $this->assertStationaryAdmin($request);
        $schoolId = app('current_school_id');

        $allocation = StationaryStudentAllocation::where('school_id', $schoolId)->find($id);
        if (!$allocation) return response()->json(['error' => 'Allocation not found.'], 404);

        $validated = $request->validate([
            'lines'                      => 'required|array|min:1',
            'lines.*.allocation_item_id' => 'required|integer',
            'lines.*.qty_issued'         => 'required|integer|min:1',
            'remarks'                    => 'nullable|string|max:2000',
        ]);

        $allocItemIds = collect($validated['lines'])->pluck('allocation_item_id')->unique()->all();
        $allocItems   = StationaryAllocationItem::whereIn('id', $allocItemIds)
            ->where('allocation_id', $allocation->id)
            ->with('item:id,name,code,unit_price')
            ->get()->keyBy('id');

        if ($allocItems->count() !== count($allocItemIds)) {
            return response()->json(['error' => 'One or more lines do not belong to this allocation.'], 422);
        }

        // Check remaining
        foreach ($validated['lines'] as $line) {
            $ai        = $allocItems[$line['allocation_item_id']];
            $remaining = (int) $ai->qty_entitled - (int) $ai->qty_collected;
            if ((int) $line['qty_issued'] > $remaining) {
                return response()->json([
                    'error' => "Cannot issue {$line['qty_issued']} of '{$ai->item?->name}' — only {$remaining} remaining.",
                ], 422);
            }
        }

        $issuance = DB::transaction(function () use ($validated, $allocation, $allocItems, $request) {
            // Lock affected stock rows to avoid concurrent over-issuance
            $itemIds = collect($validated['lines'])
                ->map(fn ($l) => $allocItems[$l['allocation_item_id']]->item_id)
                ->unique()->values();
            StationaryItem::whereIn('id', $itemIds)->lockForUpdate()->get();

            $iss = StationaryIssuance::create([
                'school_id'     => $allocation->school_id,
                'allocation_id' => $allocation->id,
                'student_id'    => $allocation->student_id,
                'issued_by'     => $request->user()->id,
                'issued_at'     => now(),
                'remarks'       => $validated['remarks'] ?? null,
            ]);

            foreach ($validated['lines'] as $line) {
                $ai  = $allocItems[$line['allocation_item_id']];
                $qty = (int) $line['qty_issued'];

                $iss->items()->create([
                    'allocation_item_id' => $ai->id,
                    'item_id'            => $ai->item_id,
                    'qty_issued'         => $qty,
                ]);
                $ai->increment('qty_collected', $qty);
                StationaryItem::where('id', $ai->item_id)->decrement('current_stock', $qty);
            }

            $allocation->update(['last_issued_date' => now()->toDateString()]);
            $allocation->refresh()->recalculateCollectionStatus();

            return $iss;
        });

        return response()->json([
            'message' => 'Items issued.',
            'data'    => $this->issuancePayload($issuance),
        ], 201);
    }

    /**
     * DELETE /mobile/stationary/issuances/{id}
     * Voids the issuance — restores qty_collected and item stock.
     */
    public function voidIssuance(Request $request, int $id): JsonResponse
    {
        $this->assertStationaryAdmin($request);
        $schoolId = app('current_school_id');

        $iss = StationaryIssuance::where('school_id', $schoolId)->find($id);
        if (!$iss) return response()->json(['error' => 'Issuance not found.'], 404);

        DB::transaction(function () use ($iss) {
            $iss->loadMissing('items');

            $itemIds      = $iss->items->pluck('item_id')->unique()->values();
            $allocItemIds = $iss->items->pluck('allocation_item_id')->unique()->values();
            StationaryItem::whereIn('id', $itemIds)->lockForUpdate()->get();
            StationaryAllocationItem::whereIn('id', $allocItemIds)->lockForUpdate()->get();

            foreach ($iss->items as $line) {
                StationaryAllocationItem::where('id', $line->allocation_item_id)
                    ->decrement('qty_collected', $line->qty_issued);
                StationaryItem::where('id', $line->item_id)
                    ->increment('current_stock', $line->qty_issued);
            }

            $iss->delete();

            $allocation = $iss->allocation()->withTrashed()->first();
            if ($allocation) {
                $allocation->recalculateCollectionStatus();
            }
        });

        return response()->json(['message' => 'Issuance voided. Stock and balances restored.', 'id' => $id]);
    }

    // ── Returns ───────────────────────────────────────────────────────────────

    private function returnPayload(StationaryReturn $r): array
    {
        $r->loadMissing(['acceptedBy:id,name', 'items.item:id,name,code']);
        return [
            'id'            => $r->id,
            'returned_at'   => $r->returned_at?->toIso8601String(),
            'accepted_by'   => $r->acceptedBy ? ['id' => $r->acceptedBy->id, 'name' => $r->acceptedBy->name] : null,
            'refund_amount' => (float) $r->refund_amount,
            'refund_mode'   => $r->refund_mode,
            'remarks'       => $r->remarks,
            'lines'         => $r->items->map(fn ($l) => [
                'id'                => $l->id,
                'item_id'           => $l->item_id,
                'item_name'         => $l->item?->name ?? 'Unknown',
                'item_code'         => $l->item?->code,
                'qty_returned'      => (int) $l->qty_returned,
                'condition'         => $l->condition,
                'restock'           => (bool) $l->restock,
                'refund_unit_price' => (float) $l->refund_unit_price,
                'line_refund'       => (float) $l->line_refund,
            ])->values(),
        ];
    }

    /**
     * GET /mobile/stationary/allocations/{id}/returns
     */
    public function returns(Request $request, int $id): JsonResponse
    {
        $this->assertStationaryAdmin($request);
        $schoolId = app('current_school_id');

        $allocation = StationaryStudentAllocation::where('school_id', $schoolId)->find($id);
        if (!$allocation) return response()->json(['error' => 'Allocation not found.'], 404);

        $list = $allocation->returns()
            ->with(['acceptedBy:id,name', 'items.item:id,name,code'])
            ->orderByDesc('returned_at')
            ->get();

        return response()->json([
            'data' => $list->map(fn ($r) => $this->returnPayload($r))->values(),
        ]);
    }

    /**
     * POST /mobile/stationary/allocations/{id}/returns
     * Body: { lines: [{ allocation_item_id, qty_returned, condition, restock }],
     *         refund_amount?, refund_mode (none|adjust|<payment_method_code>), remarks? }
     */
    public function createReturn(Request $request, int $id): JsonResponse
    {
        $this->assertStationaryAdmin($request);
        $schoolId = app('current_school_id');

        $allocation = StationaryStudentAllocation::where('school_id', $schoolId)->find($id);
        if (!$allocation) return response()->json(['error' => 'Allocation not found.'], 404);

        $validated = $request->validate([
            'lines'                      => 'required|array|min:1',
            'lines.*.allocation_item_id' => 'required|integer',
            'lines.*.qty_returned'       => 'required|integer|min:1',
            'lines.*.condition'          => 'required|in:good,damaged',
            'lines.*.restock'            => 'required|boolean',
            'refund_amount'              => 'nullable|numeric|min:0',
            'refund_mode'                => 'required|string|max:30',
            'remarks'                    => 'nullable|string|max:2000',
        ]);

        $allocItemIds = collect($validated['lines'])->pluck('allocation_item_id')->unique()->all();
        $allocItems   = StationaryAllocationItem::whereIn('id', $allocItemIds)
            ->where('allocation_id', $allocation->id)
            ->with('item:id,name,code,unit_price')
            ->get()->keyBy('id');

        if ($allocItems->count() !== count($allocItemIds)) {
            return response()->json(['error' => 'One or more lines do not belong to this allocation.'], 422);
        }

        // Validate qty + compute line refunds
        $totalLineRefund = 0;
        $linesData       = [];
        foreach ($validated['lines'] as $line) {
            $ai = $allocItems[$line['allocation_item_id']];
            if ((int) $line['qty_returned'] > (int) $ai->qty_collected) {
                return response()->json([
                    'error' => "Cannot return {$line['qty_returned']} of '{$ai->item?->name}' — only {$ai->qty_collected} were issued.",
                ], 422);
            }
            $unit       = (float) $ai->unit_price;
            $lineRefund = round($unit * (int) $line['qty_returned'], 2);
            $totalLineRefund += $lineRefund;

            $linesData[] = [
                'allocation_item_id' => $ai->id,
                'item_id'            => $ai->item_id,
                'qty_returned'       => (int) $line['qty_returned'],
                'condition'          => $line['condition'],
                'restock'            => (bool) $line['restock'],
                'refund_unit_price'  => $unit,
                'line_refund'        => $lineRefund,
            ];
        }

        $refundAmount = (float) ($validated['refund_amount'] ?? 0);
        if ($validated['refund_mode'] === 'none') {
            $refundAmount = 0;
        }
        if ($refundAmount > $totalLineRefund + 0.01) {
            return response()->json([
                'error' => "Refund amount ({$refundAmount}) cannot exceed sum of line refunds ({$totalLineRefund}).",
            ], 422);
        }

        $return = DB::transaction(function () use ($validated, $linesData, $refundAmount, $allocation, $request) {
            $itemIds = collect($linesData)->pluck('item_id')->unique()->values();
            StationaryItem::whereIn('id', $itemIds)->lockForUpdate()->get();
            StationaryAllocationItem::whereIn('id', collect($linesData)->pluck('allocation_item_id')->all())
                ->lockForUpdate()->get();

            $r = StationaryReturn::create([
                'school_id'     => $allocation->school_id,
                'allocation_id' => $allocation->id,
                'student_id'    => $allocation->student_id,
                'accepted_by'   => $request->user()->id,
                'returned_at'   => now(),
                'refund_amount' => $refundAmount,
                'refund_mode'   => $validated['refund_mode'],
                'remarks'       => $validated['remarks'] ?? null,
            ]);

            foreach ($linesData as $row) {
                $r->items()->create($row);
                StationaryAllocationItem::where('id', $row['allocation_item_id'])
                    ->decrement('qty_collected', $row['qty_returned']);
                if ($row['restock']) {
                    StationaryItem::where('id', $row['item_id'])
                        ->increment('current_stock', $row['qty_returned']);
                }
            }

            if ($refundAmount > 0 && $validated['refund_mode'] !== 'none') {
                $newPaid = max(0, (float) $allocation->amount_paid - $refundAmount);
                $allocation->update(['amount_paid' => $newPaid]);
            }

            $allocation->refresh()->recalculateTotals();
            $allocation->refresh()->recalculateCollectionStatus();

            return $r;
        });

        return response()->json([
            'message' => 'Items returned. Stock and balances updated.',
            'data'    => $this->returnPayload($return),
        ], 201);
    }

    /**
     * DELETE /mobile/stationary/returns/{id}
     * Reverses the return — re-collects qty, undoes restock, restores refund.
     */
    public function voidReturn(Request $request, int $id): JsonResponse
    {
        $this->assertStationaryAdmin($request);
        $schoolId = app('current_school_id');

        $r = StationaryReturn::where('school_id', $schoolId)->find($id);
        if (!$r) return response()->json(['error' => 'Return not found.'], 404);

        DB::transaction(function () use ($r) {
            $r->loadMissing('items');

            $itemIds      = $r->items->pluck('item_id')->unique()->values();
            $allocItemIds = $r->items->pluck('allocation_item_id')->unique()->values();
            StationaryItem::whereIn('id', $itemIds)->lockForUpdate()->get();
            StationaryAllocationItem::whereIn('id', $allocItemIds)->lockForUpdate()->get();

            foreach ($r->items as $line) {
                StationaryAllocationItem::where('id', $line->allocation_item_id)
                    ->increment('qty_collected', $line->qty_returned);
                if ($line->restock) {
                    StationaryItem::where('id', $line->item_id)
                        ->decrement('current_stock', $line->qty_returned);
                }
            }

            // Restore the refund onto the allocation
            $allocation = $r->allocation()->withTrashed()->first();
            if ($allocation && $r->refund_amount > 0 && $r->refund_mode !== 'none') {
                $allocation->update([
                    'amount_paid' => (float) $allocation->amount_paid + (float) $r->refund_amount,
                ]);
            }

            $r->delete();

            if ($allocation) {
                $allocation->recalculateTotals();
                $allocation->recalculateCollectionStatus();
            }
        });

        return response()->json(['message' => 'Return voided. Stock and balances restored.', 'id' => $id]);
    }
}
