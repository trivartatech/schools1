<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetMaintenance;
use App\Models\Department;
use App\Models\ItemStore;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class InventoryController extends Controller
{
    // ── Asset Dashboard ───────────────────────────────────────────────────
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');

        $query = Asset::where('school_id', $schoolId)
            ->with([
                'category',
                'activeAssignment',
                'supplierModel',
                'store',
                'maintenanceLogs' => fn($q) => $q->orderBy('reported_on', 'desc'),
            ]);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name', 'like', "%$s%")
                ->orWhere('asset_code', 'like', "%$s%")
                ->orWhere('serial_no', 'like', "%$s%"));
        }
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('status'))      $query->where('status', $request->status);

        $assets     = $query->latest()->paginate(25)->withQueryString();
        $categories = AssetCategory::where('school_id', $schoolId)->withCount('assets')->orderBy('name')->get();

        $stats = Asset::where('school_id', $schoolId)
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(purchase_cost) as total_cost'))
            ->groupBy('status')
            ->pluck(null, 'status');

        $openMaintenance = AssetMaintenance::where('school_id', $schoolId)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        $staff       = Staff::where('school_id', $schoolId)->with('user:id,name')->select('id', 'user_id', 'employee_id')
                           ->get()->map(fn($s) => ['id' => $s->id, 'name' => $s->user?->name ?? 'Staff #'.$s->id]);
        $sections    = Section::where('school_id', $schoolId)->select('id', 'name')->orderBy('name')->get();
        $departments = Department::where('school_id', $schoolId)->select('id', 'name')->orderBy('name')->get();
        $suppliers   = Supplier::where('school_id', $schoolId)->orderBy('name')->get(['id', 'name']);
        $stores      = ItemStore::where('school_id', $schoolId)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('School/Inventory/Index', compact(
            'assets', 'categories', 'stats', 'openMaintenance',
            'staff', 'sections', 'departments', 'suppliers', 'stores'
        ));
    }

    // ── Asset Detail ──────────────────────────────────────────────────────
    public function show(Asset $asset)
    {
        abort_if($asset->school_id !== app('current_school_id'), 403);

        $asset->load([
            'category',
            'supplierModel',
            'store',
            'assignments' => fn($q) => $q->orderBy('assigned_on', 'desc'),
            'assignments.assignedBy:id,name',
            'maintenanceLogs' => fn($q) => $q->orderBy('reported_on', 'desc'),
        ]);

        $auditLog = Activity::where('subject_type', Asset::class)
            ->where('subject_id', $asset->id)
            ->with('causer:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($a) => [
                'id'          => $a->id,
                'event'       => $a->event,
                'changes'     => $a->changes,
                'causer_name' => $a->causer?->name ?? 'System',
                'created_at'  => $a->created_at->format('d M Y, H:i'),
            ]);

        return Inertia::render('School/Inventory/Show', [
            'asset'    => array_merge($asset->toArray(), [
                'current_value'          => round($asset->current_value, 2),
                'total_maintenance_cost' => round($asset->total_maintenance_cost, 2),
            ]),
            'auditLog' => $auditLog,
        ]);
    }

    // ── Export CSV ────────────────────────────────────────────────────────
    public function export(Request $request)
    {
        $schoolId = app('current_school_id');

        $query = Asset::where('school_id', $schoolId)->with('category');
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);

        $assets  = $query->get();
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="assets_'.now()->format('Ymd').'.csv"',
            'Cache-Control'       => 'no-cache',
        ];

        $callback = function () use ($assets) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
            fputcsv($file, ['Code', 'Name', 'Category', 'Brand', 'Model', 'Serial No',
                'Purchase Date', 'Cost (₹)', 'Current Value (₹)', 'Useful Life (yrs)',
                'Depreciation Method', 'Condition', 'Status', 'Warranty Until', 'Supplier', 'Notes']);
            foreach ($assets as $a) {
                fputcsv($file, [
                    $a->asset_code, $a->name, $a->category?->name, $a->brand, $a->model_no, $a->serial_no,
                    $a->purchase_date?->format('Y-m-d'), $a->purchase_cost, round($a->current_value, 2),
                    $a->useful_life_years, $a->depreciation_method, $a->condition, $a->status,
                    $a->warranty_until, $a->supplier, $a->notes,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Import template download ──────────────────────────────────────────
    public function importTemplate()
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="asset_import_template.csv"',
        ];
        $callback = function () {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['Code', 'Name*', 'Category*', 'Brand', 'Serial No', 'Purchase Date (YYYY-MM-DD)',
                'Cost', 'Useful Life (yrs)', 'Depreciation Method (straight_line/declining_balance)',
                'Condition (excellent/good/fair/poor/condemned)', 'Notes']);
            fputcsv($file, ['LAP-001', 'Dell Latitude 5420', 'Electronics', 'Dell', 'SN123456',
                '2024-01-15', '55000', '5', 'straight_line', 'good', 'Sample row']);
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    // ── Bulk CSV Import ───────────────────────────────────────────────────
    public function import(Request $request)
    {
        $schoolId = app('current_school_id');
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:4096']);

        $handle   = fopen($request->file('file')->getPathname(), 'r');
        fgetcsv($handle); // skip header
        $imported = 0;
        $errors   = [];

        while (($row = fgetcsv($handle)) !== false) {
            $row = array_pad($row, 11, null);
            [$code, $name, $catName, $brand, $serial, $purchaseDate, $cost, $usefulLife, $deprMethod, $condition, $notes] = $row;

            $name = trim($name ?? '');
            if (!$name) continue;

            $category = null;
            if ($catName = trim($catName ?? '')) {
                $category = AssetCategory::firstOrCreate(
                    ['school_id' => $schoolId, 'name' => $catName],
                    ['description' => '']
                );
            }

            try {
                Asset::create([
                    'school_id'            => $schoolId,
                    'category_id'          => $category?->id,
                    'name'                 => $name,
                    'asset_code'           => trim($code ?? '') ?: null,
                    'brand'                => trim($brand ?? '') ?: null,
                    'serial_no'            => trim($serial ?? '') ?: null,
                    'purchase_date'        => trim($purchaseDate ?? '') ?: null,
                    'purchase_cost'        => is_numeric($cost) ? (float) $cost : 0,
                    'useful_life_years'    => is_numeric($usefulLife) ? (int) $usefulLife : 5,
                    'depreciation_method'  => in_array($deprMethod, ['straight_line', 'declining_balance']) ? $deprMethod : 'straight_line',
                    'condition'            => in_array($condition, ['excellent','good','fair','poor','condemned']) ? $condition : 'good',
                    'notes'                => trim($notes ?? '') ?: null,
                    'status'               => 'available',
                ]);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row for '{$name}': ".$e->getMessage();
            }
        }
        fclose($handle);

        $message = "$imported asset(s) imported.";
        if ($errors) $message .= ' '.count($errors).' row(s) failed.';

        return back()->with('success', $message);
    }

    // ── Store asset ───────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $schoolId  = app('current_school_id');
        $validated = $request->validate([
            'category_id'         => 'required|exists:asset_categories,id',
            'name'                => 'required|string|max:200',
            'asset_code'          => 'nullable|string|max:50',
            'brand'               => 'nullable|string|max:100',
            'model_no'            => 'nullable|string|max:100',
            'serial_no'           => 'nullable|string|max:100',
            'purchase_date'       => 'nullable|date',
            'purchase_cost'       => 'nullable|numeric|min:0',
            'supplier'            => 'nullable|string|max:200',
            'supplier_id'         => 'nullable|integer|exists:suppliers,id',
            'store_id'            => 'nullable|integer|exists:item_stores,id',
            'warranty_until'      => 'nullable|string|max:20',
            'useful_life_years'   => 'nullable|integer|min:1|max:50',
            'depreciation_method' => 'in:straight_line,declining_balance',
            'condition'           => 'in:excellent,good,fair,poor,condemned',
            'notes'               => 'nullable|string',
        ]);

        Asset::create(array_merge($validated, ['school_id' => $schoolId, 'status' => 'available']));
        return back()->with('success', 'Asset added to inventory.');
    }

    // ── Update asset ──────────────────────────────────────────────────────
    public function update(Request $request, Asset $asset)
    {
        abort_if($asset->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'category_id'         => 'required|exists:asset_categories,id',
            'name'                => 'required|string|max:200',
            'asset_code'          => 'nullable|string|max:50',
            'brand'               => 'nullable|string|max:100',
            'model_no'            => 'nullable|string|max:100',
            'serial_no'           => 'nullable|string|max:100',
            'purchase_date'       => 'nullable|date',
            'purchase_cost'       => 'nullable|numeric|min:0',
            'supplier'            => 'nullable|string|max:200',
            'supplier_id'         => 'nullable|integer|exists:suppliers,id',
            'store_id'            => 'nullable|integer|exists:item_stores,id',
            'warranty_until'      => 'nullable|string|max:20',
            'useful_life_years'   => 'nullable|integer|min:1|max:50',
            'depreciation_method' => 'in:straight_line,declining_balance',
            'condition'           => 'in:excellent,good,fair,poor,condemned',
            'notes'               => 'nullable|string',
        ]);

        $asset->update($validated);
        return back()->with('success', 'Asset updated.');
    }

    // ── Dispose asset ─────────────────────────────────────────────────────
    public function dispose(Request $request, Asset $asset)
    {
        abort_if($asset->school_id !== app('current_school_id'), 403);
        abort_if($asset->status === 'disposed', 422);

        $validated = $request->validate([
            'disposed_on'     => 'required|date',
            'disposal_reason' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($asset, $validated) {
            AssetAssignment::where('asset_id', $asset->id)->whereNull('returned_on')
                ->update(['returned_on' => $validated['disposed_on']]);
            AssetMaintenance::where('asset_id', $asset->id)->whereIn('status', ['open', 'in_progress'])
                ->update(['status' => 'scrapped', 'resolved_on' => $validated['disposed_on']]);
            $asset->update(array_merge($validated, ['status' => 'disposed']));
        });

        return back()->with('success', 'Asset marked as disposed.');
    }

    // ── Assign / Return ───────────────────────────────────────────────────
    public function assign(Request $request, Asset $asset)
    {
        abort_if($asset->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'assignee_type' => 'nullable|string|max:50',
            'assignee_id'   => 'nullable|integer',
            'assignee_name' => 'nullable|string|max:200',
            'location'      => 'required|string|max:200',
            'assigned_on'   => 'required|date',
            'notes'         => 'nullable|string',
        ]);

        DB::transaction(function () use ($asset, $validated) {
            AssetAssignment::where('asset_id', $asset->id)->whereNull('returned_on')
                ->update(['returned_on' => now()->toDateString()]);
            AssetAssignment::create(array_merge($validated, [
                'asset_id'    => $asset->id,
                'school_id'   => $asset->school_id,
                'assigned_by' => auth()->id(),
            ]));
            $asset->update(['status' => 'assigned']);
        });

        return back()->with('success', 'Asset assigned.');
    }

    public function returnAsset(Asset $asset)
    {
        abort_if($asset->school_id !== app('current_school_id'), 403);

        AssetAssignment::where('asset_id', $asset->id)->whereNull('returned_on')
            ->update(['returned_on' => now()->toDateString()]);
        $asset->update(['status' => 'available']);

        return back()->with('success', 'Asset returned to inventory.');
    }

    // ── Maintenance ───────────────────────────────────────────────────────
    public function maintenance(Request $request, Asset $asset)
    {
        abort_if($asset->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'issue_description' => 'required|string',
            'type'              => 'required|in:preventive,corrective,inspection',
            'reported_on'       => 'required|date',
        ]);

        AssetMaintenance::create(array_merge($validated, [
            'asset_id'    => $asset->id,
            'school_id'   => $asset->school_id,
            'reported_by' => auth()->id(),
            'status'      => 'open',
        ]));

        $asset->update(['status' => 'under_maintenance']);
        return back()->with('success', 'Maintenance request logged.');
    }

    public function markInProgress(AssetMaintenance $record)
    {
        abort_if($record->school_id !== app('current_school_id'), 403);
        abort_if($record->status !== 'open', 422);
        $record->update(['status' => 'in_progress']);
        return back()->with('success', 'Maintenance marked as in progress.');
    }

    public function resolveMaintenance(Request $request, AssetMaintenance $record)
    {
        abort_if($record->school_id !== app('current_school_id'), 403);

        $validated = $request->validate([
            'resolution_notes' => 'nullable|string',
            'cost'             => 'nullable|numeric|min:0',
            'vendor'           => 'nullable|string|max:200',
        ]);

        $record->update(array_merge($validated, [
            'status'      => 'resolved',
            'resolved_on' => now()->toDateString(),
        ]));

        $openCount = AssetMaintenance::where('asset_id', $record->asset_id)
            ->whereIn('status', ['open', 'in_progress'])->count();
        if ($openCount === 0) {
            $record->asset->update(['status' => 'available']);
        }

        return back()->with('success', 'Maintenance resolved.');
    }

    // ── Category CRUD ─────────────────────────────────────────────────────
    public function storeCategory(Request $request)
    {
        $schoolId = app('current_school_id');
        $request->validate(['name' => 'required|string|max:100']);
        AssetCategory::create(['school_id' => $schoolId, 'name' => $request->name, 'description' => $request->description]);
        return back()->with('success', 'Category created.');
    }

    public function updateCategory(Request $request, AssetCategory $category)
    {
        abort_if($category->school_id !== app('current_school_id'), 403);
        $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);
        $category->update(['name' => $request->name, 'description' => $request->description]);
        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(AssetCategory $category)
    {
        abort_if($category->school_id !== app('current_school_id'), 403);

        if ($category->assets()->count() > 0) {
            return back()->with('error', 'Cannot delete a category that has assets. Reassign or delete them first.');
        }

        $category->delete();
        return back()->with('success', 'Category deleted.');
    }

    // ── Reports ───────────────────────────────────────────────────────────
    public function reports()
    {
        $schoolId = app('current_school_id');

        $deprAssets = Asset::where('school_id', $schoolId)
            ->with('category:id,name')
            ->get()
            ->map(fn($a) => [
                'id'                 => $a->id,
                'name'               => $a->name,
                'asset_code'         => $a->asset_code,
                'category'           => $a->category?->name ?? '—',
                'purchase_date'      => $a->purchase_date?->format('Y-m-d'),
                'purchase_cost'      => (float) $a->purchase_cost,
                'useful_life'        => $a->useful_life_years,
                'depreciation_method'=> $a->depreciation_method,
                'current_value'      => round($a->current_value, 2),
                'depreciation'       => round(max(0, (float) $a->purchase_cost - $a->current_value), 2),
                'status'             => $a->status,
            ]);

        $maintByCategory = AssetMaintenance::where('asset_maintenance.school_id', $schoolId)
            ->join('assets', 'asset_maintenance.asset_id', '=', 'assets.id')
            ->join('asset_categories', 'assets.category_id', '=', 'asset_categories.id')
            ->select(
                'asset_categories.name as category',
                DB::raw('COUNT(*) as ticket_count'),
                DB::raw('SUM(asset_maintenance.cost) as total_cost'),
                DB::raw('SUM(CASE WHEN asset_maintenance.status IN (\'open\',\'in_progress\') THEN 1 ELSE 0 END) as open_count')
            )
            ->groupBy('asset_categories.id', 'asset_categories.name')
            ->get();

        $aging = [
            ['label' => '< 1 year',  'count' => Asset::where('school_id', $schoolId)->where('purchase_date', '>=', now()->subYear())->count()],
            ['label' => '1–3 years', 'count' => Asset::where('school_id', $schoolId)->whereBetween('purchase_date', [now()->subYears(3), now()->subYear()])->count()],
            ['label' => '3–5 years', 'count' => Asset::where('school_id', $schoolId)->whereBetween('purchase_date', [now()->subYears(5), now()->subYears(3)])->count()],
            ['label' => '5+ years',  'count' => Asset::where('school_id', $schoolId)->where('purchase_date', '<', now()->subYears(5))->count()],
            ['label' => 'No date',   'count' => Asset::where('school_id', $schoolId)->whereNull('purchase_date')->count()],
        ];

        $totals = [
            'purchase_cost' => (float) Asset::where('school_id', $schoolId)->sum('purchase_cost'),
            'current_value' => round($deprAssets->sum('current_value'), 2),
            'depreciation'  => round($deprAssets->sum('depreciation'), 2),
            'maint_cost'    => (float) AssetMaintenance::where('school_id', $schoolId)->sum('cost'),
        ];

        return Inertia::render('School/Inventory/Reports', compact(
            'deprAssets', 'maintByCategory', 'aging', 'totals'
        ));
    }
}
