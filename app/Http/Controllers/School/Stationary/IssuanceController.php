<?php

namespace App\Http\Controllers\School\Stationary;

use App\Http\Controllers\Controller;
use App\Models\StationaryAllocationItem;
use App\Models\StationaryIssuance;
use App\Models\StationaryItem;
use App\Models\StationaryStudentAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IssuanceController extends Controller
{
    public function index(StationaryStudentAllocation $allocation)
    {
        $this->authorizeAllocation($allocation);

        $issuances = $allocation->issuances()
            ->with(['issuedBy:id,name', 'items.item:id,name,code'])
            ->orderByDesc('issued_at')
            ->get();

        return response()->json(['issuances' => $issuances]);
    }

    public function store(Request $request, StationaryStudentAllocation $allocation)
    {
        $this->authorizeAllocation($allocation);

        $validated = $request->validate([
            'lines'                       => 'required|array|min:1',
            'lines.*.allocation_item_id'  => 'required|integer',
            'lines.*.qty_issued'          => 'required|integer|min:1',
            'remarks'                     => 'nullable|string|max:2000',
        ]);

        // Sum qty per allocation_item_id (in case the same line was sent twice)
        $allocItemIds = collect($validated['lines'])->pluck('allocation_item_id')->unique()->all();
        $allocItems   = StationaryAllocationItem::whereIn('id', $allocItemIds)
            ->where('allocation_id', $allocation->id)
            ->get()
            ->keyBy('id');

        if ($allocItems->count() !== count($allocItemIds)) {
            abort(422, 'One or more line items do not belong to this allocation.');
        }

        // Validate issuance amounts
        foreach ($validated['lines'] as $line) {
            $allocItem = $allocItems[$line['allocation_item_id']];
            $remaining = $allocItem->qty_entitled - $allocItem->qty_collected;
            if ($line['qty_issued'] > $remaining) {
                abort(422, "Cannot issue {$line['qty_issued']} of '{$allocItem->item?->name}' — only {$remaining} remaining.");
            }
        }

        $issuance = DB::transaction(function () use ($validated, $allocation, $allocItems) {
            // Lock affected stock rows to prevent concurrent over-issuance
            $itemIds = collect($validated['lines'])->map(fn ($l) => $allocItems[$l['allocation_item_id']]->item_id)->unique()->values();
            StationaryItem::whereIn('id', $itemIds)->lockForUpdate()->get();

            $issuance = StationaryIssuance::create([
                'school_id'     => $allocation->school_id,
                'allocation_id' => $allocation->id,
                'student_id'    => $allocation->student_id,
                'issued_by'     => auth()->id(),
                'issued_at'     => now(),
                'remarks'       => $validated['remarks'] ?? null,
            ]);

            foreach ($validated['lines'] as $line) {
                $allocItem = $allocItems[$line['allocation_item_id']];
                $qty       = (int) $line['qty_issued'];

                $issuance->items()->create([
                    'allocation_item_id' => $allocItem->id,
                    'item_id'            => $allocItem->item_id,
                    'qty_issued'         => $qty,
                ]);

                $allocItem->increment('qty_collected', $qty);
                StationaryItem::where('id', $allocItem->item_id)->decrement('current_stock', $qty);
            }

            $allocation->update(['last_issued_date' => now()->toDateString()]);
            $allocation->refresh()->recalculateCollectionStatus();

            return $issuance;
        });

        return back()->with('success', 'Items issued successfully.');
    }

    public function destroy(StationaryIssuance $issuance)
    {
        $this->authorizeTenant($issuance);

        DB::transaction(function () use ($issuance) {
            $issuance->loadMissing('items');

            // Lock affected stock + allocation_items rows
            $itemIds      = $issuance->items->pluck('item_id')->unique()->values();
            $allocItemIds = $issuance->items->pluck('allocation_item_id')->unique()->values();
            StationaryItem::whereIn('id', $itemIds)->lockForUpdate()->get();
            StationaryAllocationItem::whereIn('id', $allocItemIds)->lockForUpdate()->get();

            foreach ($issuance->items as $line) {
                StationaryAllocationItem::where('id', $line->allocation_item_id)
                    ->decrement('qty_collected', $line->qty_issued);
                StationaryItem::where('id', $line->item_id)
                    ->increment('current_stock', $line->qty_issued);
            }

            $issuance->delete(); // soft-delete

            // Recompute allocation collection status
            $allocation = $issuance->allocation()->withTrashed()->first();
            if ($allocation) {
                $allocation->recalculateCollectionStatus();
            }
        });

        return back()->with('success', 'Issuance voided. Stock and qty_collected restored.');
    }

    private function authorizeAllocation(StationaryStudentAllocation $allocation): void
    {
        abort_unless($allocation->school_id === app('current_school_id'), 403);
    }

    private function authorizeTenant(StationaryIssuance $issuance): void
    {
        abort_unless($issuance->school_id === app('current_school_id'), 403);
    }
}
