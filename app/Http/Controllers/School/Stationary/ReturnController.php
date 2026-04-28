<?php

namespace App\Http\Controllers\School\Stationary;

use App\Http\Controllers\Controller;
use App\Models\StationaryAllocationItem;
use App\Models\StationaryItem;
use App\Models\StationaryReturn;
use App\Models\StationaryStudentAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    public function index(StationaryStudentAllocation $allocation)
    {
        $this->authorizeAllocation($allocation);

        $returns = $allocation->returns()
            ->with(['acceptedBy:id,name', 'items.item:id,name,code'])
            ->orderByDesc('returned_at')
            ->get();

        return response()->json(['returns' => $returns]);
    }

    public function store(Request $request, StationaryStudentAllocation $allocation)
    {
        $this->authorizeAllocation($allocation);

        $validated = $request->validate([
            'lines'                          => 'required|array|min:1',
            'lines.*.allocation_item_id'     => 'required|integer',
            'lines.*.qty_returned'           => 'required|integer|min:1',
            'lines.*.condition'              => 'required|in:good,damaged',
            'lines.*.restock'                => 'required|boolean',
            'refund_amount'                  => 'nullable|numeric|min:0',
            // 'none' = no refund, 'adjust' = applied against future balance (no GL).
            // Anything else must be an active payment method code for this school.
            'refund_mode'                    => [
                'required', 'string',
                function ($attribute, $value, $fail) use ($allocation) {
                    if (in_array($value, ['none', 'adjust'], true)) return;
                    $exists = \App\Models\PaymentMethod::where('school_id', $allocation->school_id)
                        ->where('code', $value)
                        ->where('is_active', true)
                        ->exists();
                    if (! $exists) {
                        $fail('The selected refund mode is invalid.');
                    }
                },
            ],
            'remarks'                        => 'nullable|string|max:2000',
        ]);

        $allocItemIds = collect($validated['lines'])->pluck('allocation_item_id')->unique()->all();
        $allocItems   = StationaryAllocationItem::whereIn('id', $allocItemIds)
            ->where('allocation_id', $allocation->id)
            ->with('item:id,name,code,unit_price')
            ->get()
            ->keyBy('id');

        if ($allocItems->count() !== count($allocItemIds)) {
            abort(422, 'One or more line items do not belong to this allocation.');
        }

        // Validate qty + compute line refunds
        $totalLineRefund = 0;
        $linesData       = [];
        foreach ($validated['lines'] as $line) {
            $allocItem = $allocItems[$line['allocation_item_id']];
            if ($line['qty_returned'] > $allocItem->qty_collected) {
                abort(422, "Cannot return {$line['qty_returned']} of '{$allocItem->item?->name}' — only {$allocItem->qty_collected} were issued.");
            }

            $unit       = (float) $allocItem->unit_price;
            $lineRefund = round($unit * (int) $line['qty_returned'], 2);
            $totalLineRefund += $lineRefund;

            $linesData[] = [
                'allocation_item_id' => $allocItem->id,
                'item_id'            => $allocItem->item_id,
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
            abort(422, "Refund amount ({$refundAmount}) cannot exceed sum of line refunds ({$totalLineRefund}).");
        }

        DB::transaction(function () use ($validated, $linesData, $refundAmount, $allocation, $allocItems) {
            // Lock stock + allocation_items
            $itemIds = collect($linesData)->pluck('item_id')->unique()->values();
            StationaryItem::whereIn('id', $itemIds)->lockForUpdate()->get();
            StationaryAllocationItem::whereIn('id', collect($linesData)->pluck('allocation_item_id')->all())->lockForUpdate()->get();

            $return = StationaryReturn::create([
                'school_id'     => $allocation->school_id,
                'allocation_id' => $allocation->id,
                'student_id'    => $allocation->student_id,
                'accepted_by'   => auth()->id(),
                'returned_at'   => now(),
                'refund_amount' => $refundAmount,
                'refund_mode'   => $validated['refund_mode'],
                'remarks'       => $validated['remarks'] ?? null,
            ]);

            foreach ($linesData as $row) {
                $return->items()->create($row);

                StationaryAllocationItem::where('id', $row['allocation_item_id'])
                    ->decrement('qty_collected', $row['qty_returned']);

                if ($row['restock']) {
                    StationaryItem::where('id', $row['item_id'])
                        ->increment('current_stock', $row['qty_returned']);
                }
            }

            // Decrement allocation.amount_paid for any refund (cash, cheque, OR adjust).
            // For 'cash'/'cheque' the GL observer also posts the reverse entry.
            // For 'adjust' it's a logical credit only.
            if ($refundAmount > 0 && $validated['refund_mode'] !== 'none') {
                $newPaid = max(0, (float) $allocation->amount_paid - $refundAmount);
                $allocation->update(['amount_paid' => $newPaid]);
            }

            $allocation->refresh()->recalculateTotals();
            $allocation->refresh()->recalculateCollectionStatus();
        });

        return back()->with('success', 'Items returned. Stock and balances updated.');
    }

    public function destroy(StationaryReturn $return)
    {
        $this->authorizeTenant($return);

        DB::transaction(function () use ($return) {
            $return->loadMissing('items');

            $itemIds      = $return->items->pluck('item_id')->unique()->values();
            $allocItemIds = $return->items->pluck('allocation_item_id')->unique()->values();
            StationaryItem::whereIn('id', $itemIds)->lockForUpdate()->get();
            StationaryAllocationItem::whereIn('id', $allocItemIds)->lockForUpdate()->get();

            foreach ($return->items as $line) {
                StationaryAllocationItem::where('id', $line->allocation_item_id)
                    ->increment('qty_collected', $line->qty_returned);

                if ($line->restock) {
                    StationaryItem::where('id', $line->item_id)
                        ->decrement('current_stock', $line->qty_returned);
                }
            }

            // Restore allocation.amount_paid for refunds
            if ((float) $return->refund_amount > 0 && $return->refund_mode !== 'none') {
                $allocation = $return->allocation;
                if ($allocation) {
                    $allocation->increment('amount_paid', $return->refund_amount);
                }
            }

            $return->delete(); // soft delete — observer posts the reverse GL entry

            $allocation = $return->allocation()->first();
            if ($allocation) {
                $allocation->refresh()->recalculateTotals();
                $allocation->refresh()->recalculateCollectionStatus();
            }
        });

        return back()->with('success', 'Return voided. Stock, qty_collected, and balances restored.');
    }

    private function authorizeAllocation(StationaryStudentAllocation $allocation): void
    {
        abort_unless($allocation->school_id === app('current_school_id'), 403);
    }

    private function authorizeTenant(StationaryReturn $return): void
    {
        abort_unless($return->school_id === app('current_school_id'), 403);
    }
}
