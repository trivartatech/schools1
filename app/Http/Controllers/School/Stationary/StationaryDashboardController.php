<?php

namespace App\Http\Controllers\School\Stationary;

use App\Http\Controllers\Controller;
use App\Models\StationaryFeePayment;
use App\Models\StationaryItem;
use App\Models\StationaryReturn;
use App\Models\StationaryStudentAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class StationaryDashboardController extends Controller
{
    public function index()
    {
        $today      = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        $stats = [
            'item_count'         => StationaryItem::tenant()->count(),
            'active_items'       => StationaryItem::tenant()->where('status', 'active')->count(),
            'low_stock'          => StationaryItem::tenant()
                                        ->whereColumn('current_stock', '<=', 'min_stock')
                                        ->where('status', 'active')->count(),
            'allocations'        => StationaryStudentAllocation::tenant()->count(),
            'unpaid_count'       => StationaryStudentAllocation::tenant()->where('payment_status', 'unpaid')->count(),
            'partial_count'      => StationaryStudentAllocation::tenant()->where('payment_status', 'partial')->count(),
            'collection_pending' => StationaryStudentAllocation::tenant()->whereIn('collection_status', ['none', 'partial'])->count(),

            'today_collection'   => (float) StationaryFeePayment::tenant()->whereDate('payment_date', $today)->sum('amount_paid'),
            'month_collection'   => (float) StationaryFeePayment::tenant()->whereDate('payment_date', '>=', $monthStart)->sum('amount_paid'),
            'today_refunds'      => (float) StationaryReturn::tenant()->whereDate('returned_at', $today)->sum('refund_amount'),
            'month_refunds'      => (float) StationaryReturn::tenant()->whereDate('returned_at', '>=', $monthStart)->sum('refund_amount'),

            'total_outstanding'  => (float) StationaryStudentAllocation::tenant()->sum('balance'),
        ];

        $lowStockItems = StationaryItem::tenant()
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->where('status', 'active')
            ->orderBy('current_stock')
            ->limit(10)
            ->get(['id', 'name', 'code', 'current_stock', 'min_stock']);

        return Inertia::render('School/Stationary/Dashboard', [
            'stats'         => $stats,
            'lowStockItems' => $lowStockItems,
        ]);
    }

    public function feeDefaulters()
    {
        $defaulters = StationaryStudentAllocation::tenant()
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('balance', '>', 0)
            ->where('status', 'active')
            ->with([
                'student:id,admission_no,first_name,last_name,user_id',
                'student.user:id,name',
                'student.currentAcademicHistory.courseClass:id,name',
                'student.currentAcademicHistory.section:id,name',
            ])
            ->orderByDesc('balance')
            ->get();

        return Inertia::render('School/Stationary/Reports/FeeDefaulters', [
            'defaulters' => $defaulters,
        ]);
    }

    public function collectionPending()
    {
        $pending = StationaryStudentAllocation::tenant()
            ->whereIn('collection_status', ['none', 'partial'])
            ->where('status', 'active')
            ->with([
                'student:id,admission_no,first_name,last_name,user_id',
                'student.user:id,name',
                'student.currentAcademicHistory.courseClass:id,name',
                'student.currentAcademicHistory.section:id,name',
                'lineItems',
            ])
            ->get();

        return Inertia::render('School/Stationary/Reports/CollectionPending', [
            'pending' => $pending,
        ]);
    }

    public function returnsReport(Request $request)
    {
        $from = $request->input('from', Carbon::now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   Carbon::today()->toDateString());

        $returns = StationaryReturn::tenant()
            ->whereDate('returned_at', '>=', $from)
            ->whereDate('returned_at', '<=', $to)
            ->with([
                'student:id,admission_no,first_name,last_name,user_id',
                'student.user:id,name',
                'acceptedBy:id,name',
                'items.item:id,name,code',
            ])
            ->orderByDesc('returned_at')
            ->get();

        $summary = [
            'count'          => $returns->count(),
            'refund_total'   => (float) $returns->sum('refund_amount'),
            'qty_total'      => (int)   $returns->sum(fn ($r) => $r->items->sum('qty_returned')),
            'restock_qty'    => (int)   $returns->sum(fn ($r) => $r->items->where('restock', true)->sum('qty_returned')),
            'writeoff_qty'   => (int)   $returns->sum(fn ($r) => $r->items->where('restock', false)->sum('qty_returned')),
        ];

        return Inertia::render('School/Stationary/Reports/Returns', [
            'returns' => $returns,
            'summary' => $summary,
            'filters' => compact('from', 'to'),
        ]);
    }
}
