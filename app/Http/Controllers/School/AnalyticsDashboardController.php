<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Inertia\Inertia;

class AnalyticsDashboardController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    public function index()
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $safe = fn(callable $fn) => rescue($fn, []);

        return Inertia::render('School/Analytics/Dashboard', [
            'attendanceTrend'   => $safe(fn() => $this->analytics->attendanceTrend($schoolId, $academicYearId)),
            'feeCollection'     => $safe(fn() => $this->analytics->feeCollectionTrend($schoolId, $academicYearId)),
            'enrollmentByClass' => $safe(fn() => $this->analytics->enrollmentByClass($schoolId, $academicYearId)),
            'examPerformance'   => $safe(fn() => $this->analytics->examPerformance($schoolId, $academicYearId)),
            'staffLeaveHeatmap' => $safe(fn() => $this->analytics->staffLeaveHeatmap($schoolId)),
            'summary'           => $safe(fn() => $this->analytics->summaryStats($schoolId, $academicYearId)),
        ]);
    }
}
