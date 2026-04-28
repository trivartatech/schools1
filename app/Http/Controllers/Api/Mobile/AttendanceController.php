<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve which student's data to serve.
     * For parents with multiple children, honour the X-Active-Student-Id header
     * or `student_id` query param. Always validates ownership.
     */
    private function resolveStudentId($user, ?Request $request = null): ?int
    {
        if ($user->isStudent()) {
            return $user->student?->id;
        }

        if ($user->isParent()) {
            $parent   = $user->studentParent;
            if (!$parent) return null;

            $children = $parent->students()->pluck('id');
            if ($children->isEmpty()) return null;

            // Check for explicit child selection
            $requested = $request?->header('X-Active-Student-Id')
                      ?? $request?->input('student_id');

            if ($requested && $children->contains((int)$requested)) {
                return (int)$requested;
            }

            // Default: first child
            return $children->first();
        }

        return null;
    }

    // ── Attendance ────────────────────────────────────────────────────────────

    public function attendance(Request $request): JsonResponse
    {
        $user   = $request->user();
        $yearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        $month  = $request->input('month', now()->format('Y-m'));

        [$year, $mon] = explode('-', $month);
        $from = Carbon::createFromDate((int)$year, (int)$mon, 1)->startOfMonth();
        $to   = $from->copy()->endOfMonth();

        $studentId = $this->resolveStudentId($user, $request);
        if (!$studentId) {
            return response()->json(['summary' => [], 'records' => []]);
        }

        $records = Attendance::where('student_id', $studentId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get(['date', 'status', 'remarks']);

        $summary = [
            'present'        => $records->where('status', 'present')->count(),
            'absent'         => $records->where('status', 'absent')->count(),
            'late'           => $records->where('status', 'late')->count(),
            'half_day'       => $records->where('status', 'half_day')->count(),
            'leave'          => $records->where('status', 'leave')->count(),
            'total'          => $records->count(),
        ];
        // Canonical formula: (present + late*0.5 + half_day*0.5) / total
        $summary['attendance_pct'] = $summary['total'] > 0
            ? round(($summary['present'] + $summary['late'] * 0.5 + $summary['half_day'] * 0.5) / $summary['total'] * 100, 1)
            : 100;

        return response()->json([
            'summary'    => $summary,
            'records'    => $records,
            'student_id' => $studentId,
        ]);
    }
}
