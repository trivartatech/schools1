<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'log_name' => 'nullable|string|max:100',
            'search'   => 'nullable|string|max:200',
            'date'     => 'nullable|date_format:Y-m-d',
        ]);

        $query = Activity::where('school_id', $schoolId)
            ->with(['causer', 'subject'])
            ->latest();

        if (!empty($validated['log_name'])) {
            $query->where('log_name', $validated['log_name']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('properties', 'like', "%{$search}%");
            });
        }

        if (!empty($validated['date'])) {
            $query->whereDate('created_at', $validated['date']);
        }

        $logs = $query->paginate(30)->withQueryString();

        $logNames = Activity::where('school_id', $schoolId)
            ->distinct()
            ->pluck('log_name');

        return Inertia::render('School/Utility/ActivityLog', [
            'logs'              => $logs,
            'filters'           => $request->only(['log_name', 'search', 'date']),
            'availableLogNames' => $logNames,
        ]);
    }
}
