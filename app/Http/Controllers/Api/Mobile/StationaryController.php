<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\StationaryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
