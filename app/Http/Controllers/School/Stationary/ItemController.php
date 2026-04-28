<?php

namespace App\Http\Controllers\School\Stationary;

use App\Http\Controllers\Controller;
use App\Models\StationaryItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $status = $request->input('status', '');

        $query = StationaryItem::tenant()
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status);
        }

        $items = $query->paginate(20)->withQueryString();

        // Stats card data
        $stats = [
            'total'      => StationaryItem::tenant()->count(),
            'active'     => StationaryItem::tenant()->where('status', 'active')->count(),
            'low_stock'  => StationaryItem::tenant()
                                ->whereColumn('current_stock', '<=', 'min_stock')
                                ->where('status', 'active')
                                ->count(),
        ];

        return Inertia::render('School/Stationary/Items/Index', [
            'items'   => $items,
            'filters' => ['q' => $search, 'status' => $status],
            'stats'   => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'code'          => 'nullable|string|max:50|unique:stationary_items,code,NULL,id,school_id,' . $schoolId,
            'unit_price'    => 'required|numeric|min:0',
            'hsn_code'      => 'nullable|string|max:30',
            'current_stock' => 'required|integer|min:0',
            'min_stock'     => 'required|integer|min:0',
            'status'        => 'required|in:active,inactive',
            'description'   => 'nullable|string|max:2000',
        ]);

        StationaryItem::create([...$validated, 'school_id' => $schoolId]);

        return back()->with('success', 'Stationary item added.');
    }

    public function update(Request $request, StationaryItem $item)
    {
        $this->authorizeTenant($item);
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'code'          => 'nullable|string|max:50|unique:stationary_items,code,' . $item->id . ',id,school_id,' . $schoolId,
            'unit_price'    => 'required|numeric|min:0',
            'hsn_code'      => 'nullable|string|max:30',
            'current_stock' => 'required|integer|min:0',
            'min_stock'     => 'required|integer|min:0',
            'status'        => 'required|in:active,inactive',
            'description'   => 'nullable|string|max:2000',
        ]);

        $item->update($validated);

        return back()->with('success', 'Stationary item updated.');
    }

    public function destroy(StationaryItem $item)
    {
        $this->authorizeTenant($item);

        // Block delete if any allocation references this item — keeps history intact.
        if ($item->allocationItems()->exists()) {
            return back()->withErrors([
                'item' => 'This item is referenced by one or more student allocations and cannot be deleted. Mark it inactive instead.',
            ]);
        }

        $item->delete();
        return back()->with('success', 'Stationary item deleted.');
    }

    private function authorizeTenant(StationaryItem $item): void
    {
        abort_unless($item->school_id === app('current_school_id'), 403);
    }
}
