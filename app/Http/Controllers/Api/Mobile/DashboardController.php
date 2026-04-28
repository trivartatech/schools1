<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\ExamSchedule;
use App\Models\FeePayment;
use App\Models\Student;
use App\Services\FeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected FeeService $feeService) {}

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

    private function childList($user): array
    {
        $parent = $user->studentParent;
        if (!$parent) return [];

        return $parent->students()
            ->with(['currentAcademicHistory.courseClass', 'currentAcademicHistory.section'])
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'name'         => $s->name,
                'admission_no' => $s->admission_no,
                'photo_url'    => $s->photo_url,
                'class'        => $s->currentAcademicHistory?->courseClass?->name ?? '',
                'section'      => $s->currentAcademicHistory?->section?->name ?? '',
            ])
            ->toArray();
    }

    private function userData($user): array
    {
        return [
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'phone'     => $user->phone,
            'user_type' => $user->user_type,
            'avatar'    => $user->avatar,
            'school_id' => $user->school_id,
        ];
    }

    private function schoolData($school): array
    {
        return [
            'id'       => $school->id,
            'name'     => $school->name,
            'logo'     => $school->logo ? asset('storage/' . $school->logo) : null,
            'currency' => $school->currency ?? '₹',
            'features' => $school->features ?? [],
            'settings' => collect($school->settings ?? [])->only([
                'app_name', 'app_description', 'address_line1', 'address_line2',
                'zipcode', 'country', 'date_format', 'time_format',
            ])->toArray(),
        ];
    }

    private function stats($user, ?int $yearId, Request $request): array
    {
        $school = app('current_school');

        if ($user->isStudent() || $user->isParent()) {
            $studentId = $this->resolveStudentId($user, $request);
            if (!$studentId || !$yearId) return [];

            $totalAtt   = Attendance::where('student_id', $studentId)->where('academic_year_id', $yearId)->count();
            $presentAtt = Attendance::where('student_id', $studentId)->where('academic_year_id', $yearId)
                ->whereIn('status', ['present', 'late', 'half_day'])->count();
            $student    = Student::find($studentId);
            $feeSummary = $student ? $this->feeService->getStudentFeeSummary($student, $yearId, $school->id) : [];

            return [
                'attendance_pct'      => $totalAtt > 0 ? round($presentAtt / $totalAtt * 100) : 0,
                'fee_balance'         => $feeSummary['balance']   ?? 0,
                'pending_assignments' => 0,
                'upcoming_exams'      => ExamSchedule::where('school_id', $school->id)
                    ->where('status', 'published')->where('academic_year_id', $yearId)->count(),
            ];
        }

        if ($user->isTeacher()) {
            return [
                'classes_today'  => 0,
                'total_students' => Student::where('school_id', $school->id)->enrolledInYear($yearId)->count(),
                'pending_marks'  => 0,
                'leave_requests' => 0,
            ];
        }

        $totalStudents  = Student::where('school_id', $school->id)->where('status', 'active')->enrolledInYear($yearId)->count();
        $markedToday    = Attendance::where('school_id', $school->id)->whereDate('date', today())->count();
        $presentToday   = Attendance::where('school_id', $school->id)->whereDate('date', today())
            ->whereIn('status', ['present', 'late', 'half_day'])->count();

        // Today's fee collection — sum across all four fee streams.
        $sumToday = fn (string $modelClass) => (float) $modelClass::where('school_id', $school->id)
            ->whereDate('payment_date', today())->sum('amount_paid');
        $feeCollectedToday = $sumToday(FeePayment::class)
            + $sumToday(\App\Models\TransportFeePayment::class)
            + $sumToday(\App\Models\HostelFeePayment::class)
            + $sumToday(\App\Models\StationaryFeePayment::class);

        return [
            'total_students'       => $totalStudents,
            'total_staff'          => \App\Models\Staff::where('school_id', $school->id)->count(),
            'fee_collected_today'  => $feeCollectedToday,
            'today_attendance_pct' => $markedToday > 0 ? round($presentToday / max(1, $totalStudents) * 100, 1) : 0,
            'today_unmarked'       => max(0, $totalStudents - $markedToday),
        ];
    }

    private function recentAnnouncements(int $schoolId, int $limit): array
    {
        return Announcement::where('school_id', $schoolId)->where('is_broadcasted', true)
            ->orderByDesc('created_at')->limit($limit)
            ->get(['id', 'title', 'delivery_method', 'created_at'])->toArray();
    }

    private function attendanceSummary($user, ?int $yearId, Request $request): array
    {
        $studentId = $this->resolveStudentId($user, $request);
        if (!$studentId || !$yearId) return [];

        // Today's attendance for dashboard display
        $todayRecord = Attendance::where('student_id', $studentId)
            ->where('academic_year_id', $yearId)
            ->whereDate('date', today())
            ->first();

        // Current month records for breakdown
        $records = Attendance::where('student_id', $studentId)
            ->where('academic_year_id', $yearId)
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
            ->get();

        $present  = $records->where('status', 'present')->count();
        $absent   = $records->where('status', 'absent')->count();
        $late     = $records->where('status', 'late')->count();
        $half_day = $records->where('status', 'half_day')->count();
        $leave    = $records->where('status', 'leave')->count();
        $total    = $records->count();

        return [
            'present'        => $present,
            'absent'         => $absent,
            'late'           => $late,
            'half_day'       => $half_day,
            'leave'          => $leave,
            'total'          => $total,
            'attendance_pct' => $total > 0 ? round(($present + $late + ($half_day * 0.5)) / $total * 100) : 0,
            'today_status'   => $todayRecord?->status ?? 'not_marked',
            'month'          => now()->format('M Y'),
        ];
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────
    // For parents: returns data for the ACTIVE child (selected via switcher)
    // Header: X-Active-Student-Id (optional — defaults to first child)

    public function dashboard(Request $request): JsonResponse
    {
        $user    = $request->user();
        $school  = app('current_school');
        $yearId  = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        return response()->json([
            'user'          => $this->userData($user),
            'school'        => $this->schoolData($school),
            'stats'         => $this->stats($user, $yearId, $request),
            'announcements' => $this->recentAnnouncements($school->id, 5),
            'attendance'    => $this->attendanceSummary($user, $yearId, $request),
            // Parents get the full child list for the switcher
            'children'      => $user->isParent() ? $this->childList($user) : null,
        ]);
    }

    // ── Child List (parent multi-child) ───────────────────────────────────────

    public function children(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isParent()) {
            return response()->json(['children' => []]);
        }
        return response()->json(['children' => $this->childList($user)]);
    }
}
