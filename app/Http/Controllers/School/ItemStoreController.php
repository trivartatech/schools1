<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ItemStore;
use App\Models\StoreItem;
use App\Models\StoreTransaction;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ItemStoreController extends Controller
{
    private function schoolId(): int
    {
        return (int) app('current_school_id');
    }

    // ── Stores ────────────────────────────────────────────────────────────────

    public function index()
    {
        $stores = ItemStore::where('school_id', $this->schoolId())
            ->withCount('items')
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::where('school_id', $this->schoolId())
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('School/Inventory/Stores', [
            'stores'    => $stores,
            'suppliers' => $suppliers,
        ]);
    }

    public function storeStore(Request $request)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:200',
            'location'          => 'nullable|string|max:200',
            'incharge_staff_id' => 'nullable|integer',
            'description'       => 'nullable|string|max:500',
        ]);

        ItemStore::create(['school_id' => $this->schoolId()] + $data);

        return back()->with('success', 'Store created.');
    }

    public function updateStore(Request $request, ItemStore $store)
    {
        abort_unless($store->school_id === $this->schoolId(), 403);

        $data = $request->validate([
            'name'              => 'required|string|max:200',
            'location'          => 'nullable|string|max:200',
            'incharge_staff_id' => 'nullable|integer',
            'description'       => 'nullable|string|max:500',
        ]);

        $store->update($data);

        return back()->with('success', 'Store updated.');
    }

    public function destroyStore(ItemStore $store)
    {
        abort_unless($store->school_id === $this->schoolId(), 403);

        if ($store->items()->count() > 0) {
            return back()->withErrors(['store' => 'Cannot delete — store has items. Remove items first.']);
        }

        $store->delete();

        return back()->with('success', 'Store deleted.');
    }

    // ── Items ─────────────────────────────────────────────────────────────────

    public function show(ItemStore $store)
    {
        abort_unless($store->school_id === $this->schoolId(), 403);

        $store->load(['items.supplier']);

        $suppliers = Supplier::where('school_id', $this->schoolId())
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('School/Inventory/StoreDetail', [
            'store'     => $store,
            'suppliers' => $suppliers,
        ]);
    }

    public function storeItem(Request $request, ItemStore $store)
    {
        abort_unless($store->school_id === $this->schoolId(), 403);

        $data = $request->validate([
            'name'         => 'required|string|max:200',
            'unit'         => 'required|string|max:50',
            'supplier_id'  => 'nullable|integer',
            'quantity'     => 'nullable|numeric|min:0',
            'min_quantity' => 'nullable|numeric|min:0',
            'unit_price'   => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string|max:500',
        ]);

        $data['store_id']  = $store->id;
        $data['school_id'] = $this->schoolId();
        $data['quantity']  = $data['quantity'] ?? 0;

        StoreItem::create($data);

        return back()->with('success', 'Item added.');
    }

    public function updateItem(Request $request, StoreItem $item)
    {
        abort_unless($item->school_id === $this->schoolId(), 403);

        $data = $request->validate([
            'name'         => 'required|string|max:200',
            'unit'         => 'required|string|max:50',
            'supplier_id'  => 'nullable|integer',
            'min_quantity' => 'nullable|numeric|min:0',
            'unit_price'   => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string|max:500',
        ]);

        $item->update($data);

        return back()->with('success', 'Item updated.');
    }

    public function destroyItem(StoreItem $item)
    {
        abort_unless($item->school_id === $this->schoolId(), 403);

        $item->delete();

        return back()->with('success', 'Item deleted.');
    }

    // ── Transactions (stock in / out) ─────────────────────────────────────────

    public function transaction(Request $request, StoreItem $item)
    {
        abort_unless($item->school_id === $this->schoolId(), 403);

        $data = $request->validate([
            'type'             => 'required|in:in,out',
            'quantity'         => 'required|numeric|min:0.01',
            'reference'        => 'nullable|string|max:200',
            'notes'            => 'nullable|string|max:500',
            'transaction_date' => 'required|date',
        ]);

        if ($data['type'] === 'out' && $item->quantity < $data['quantity']) {
            return back()->withErrors(['quantity' => 'Insufficient stock. Available: ' . $item->quantity . ' ' . $item->unit]);
        }

        DB::transaction(function () use ($item, $data) {
            StoreTransaction::create([
                'school_id'        => $item->school_id,
                'store_id'         => $item->store_id,
                'item_id'          => $item->id,
                'type'             => $data['type'],
                'quantity'         => $data['quantity'],
                'reference'        => $data['reference'] ?? null,
                'notes'            => $data['notes'] ?? null,
                'created_by'       => auth()->id(),
                'transaction_date' => $data['transaction_date'],
            ]);

            $delta = $data['type'] === 'in' ? $data['quantity'] : -$data['quantity'];
            $item->increment('quantity', $delta);
        });

        return back()->with('success', ucfirst($data['type']) . ' transaction recorded.');
    }
}
