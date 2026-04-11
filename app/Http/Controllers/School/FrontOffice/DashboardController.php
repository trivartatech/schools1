<?php

namespace App\Http\Controllers\School\FrontOffice;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Complaint;
use App\Models\Correspondence;
use App\Models\GatePass;
use App\Models\VisitorLog;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $schoolId = app('current_school_id');
        $today = Carbon::today()->toDateString();

        // Auto-escalate breached complaints
        $this->autoEscalateComplaints($schoolId);

        $stats = [
            'today_visitors'     => VisitorLog::where('school_id', $schoolId)->whereDate('in_time', $today)->count(),
            'visitors_in'        => VisitorLog::where('school_id', $schoolId)->whereDate('in_time', $today)->whereNull('out_time')->count(),
            'expected_visitors'  => VisitorLog::where('school_id', $schoolId)->where('is_pre_registered', true)->where('expected_date', $today)->whereNull('in_time')->count(),
            'pending_passes'     => GatePass::where('school_id', $schoolId)->where('status', 'Pending')->count(),
            'active_passes'      => GatePass::where('school_id', $schoolId)->whereIn('status', ['Approved', 'Exited'])->count(),
            'open_complaints'    => Complaint::where('school_id', $schoolId)->whereIn('status', ['Open', 'In Progress'])->count(),
            'sla_breached'       => Complaint::where('school_id', $schoolId)->where('sla_breached', true)->whereIn('status', ['Open', 'In Progress'])->count(),
            'overdue_followups'  => CallLog::where('school_id', $schoolId)->where('follow_up_completed', false)->whereNotNull('follow_up_date')->where('follow_up_date', '<', $today)->count(),
            'today_followups'    => CallLog::where('school_id', $schoolId)->where('follow_up_completed', false)->whereNotNull('follow_up_date')->where('follow_up_date', $today)->count(),
            'pending_mail'       => Correspondence::where('school_id', $schoolId)->where('delivery_status', 'pending')->count(),
        ];

        // Recent activity (last 10 items across modules)
        $recentVisitors = VisitorLog::where('school_id', $schoolId)
            ->whereDate('in_time', $today)
            ->latest('in_time')
            ->take(5)
            ->get(['id', 'name', 'purpose', 'in_time', 'out_time'])
            ->map(fn($v) => ['type' => 'visitor', 'title' => $v->name, 'detail' => $v->purpose, 'time' => $v->in_time, 'status' => $v->out_time ? 'Exited' : 'In']);

        $recentPasses = GatePass::where('school_id', $schoolId)
            ->latest()
            ->take(5)
            ->get(['id', 'pass_type', 'status', 'created_at', 'picked_up_by_name'])
            ->map(fn($p) => ['type' => 'gate_pass', 'title' => $p->picked_up_by_name ?: $p->pass_type . ' Pass', 'detail' => $p->pass_type, 'time' => $p->created_at, 'status' => $p->status]);

        $recentComplaints = Complaint::where('school_id', $schoolId)
            ->latest()
            ->take(5)
            ->get(['id', 'type', 'status', 'priority', 'created_at', 'sla_breached'])
            ->map(fn($c) => ['type' => 'complaint', 'title' => $c->type . ' Complaint', 'detail' => $c->priority, 'time' => $c->created_at, 'status' => $c->status, 'sla_breached' => $c->sla_breached]);

        $activity = $recentVisitors->concat($recentPasses)->concat($recentComplaints)
            ->sortByDesc('time')->take(10)->values();

        return Inertia::render('School/FrontOffice/Dashboard', [
            'stats'    => $stats,
            'activity' => $activity,
        ]);
    }

    /**
     * Daily Activity Report — aggregated stats for a given date.
     */
    public function dailyReport()
    {
        $schoolId = app('current_school_id');
        $date = request('date', Carbon::today()->toDateString());

        $visitors = VisitorLog::where('school_id', $schoolId)->whereDate('in_time', $date);
        $visitorStats = [
            'total'       => (clone $visitors)->count(),
            'signed_out'  => (clone $visitors)->whereNotNull('out_time')->count(),
            'still_in'    => (clone $visitors)->whereNull('out_time')->count(),
            'by_purpose'  => (clone $visitors)->get()->groupBy('purpose')->map->count(),
        ];

        $passes = GatePass::where('school_id', $schoolId)->whereDate('created_at', $date);
        $passStats = [
            'total'    => (clone $passes)->count(),
            'by_status'=> (clone $passes)->get()->groupBy('status')->map->count(),
            'by_type'  => (clone $passes)->get()->groupBy('pass_type')->map->count(),
        ];

        $complaints = Complaint::where('school_id', $schoolId)->whereDate('created_at', $date);
        $complaintStats = [
            'new'           => (clone $complaints)->count(),
            'resolved_today'=> Complaint::where('school_id', $schoolId)->whereDate('resolved_at', $date)->count(),
            'by_priority'   => (clone $complaints)->get()->groupBy('priority')->map->count(),
            'sla_breached'  => (clone $complaints)->where('sla_breached', true)->count(),
        ];

        $calls = CallLog::where('school_id', $schoolId)->whereDate('created_at', $date);
        $callStats = [
            'total'      => (clone $calls)->count(),
            'by_type'    => (clone $calls)->get()->groupBy('call_type')->map->count(),
            'by_purpose' => (clone $calls)->get()->groupBy('purpose')->map->count(),
            'followups_due' => CallLog::where('school_id', $schoolId)->where('follow_up_date', $date)->where('follow_up_completed', false)->count(),
        ];

        $mail = Correspondence::where('school_id', $schoolId)->whereDate('date', $date);
        $mailStats = [
            'total'   => (clone $mail)->count(),
            'by_type' => (clone $mail)->get()->groupBy('type')->map->count(),
        ];

        return Inertia::render('School/FrontOffice/DailyReport', [
            'date'       => $date,
            'visitors'   => $visitorStats,
            'passes'     => $passStats,
            'complaints' => $complaintStats,
            'calls'      => $callStats,
            'mail'       => $mailStats,
        ]);
    }

    /**
     * Auto-escalate complaints that have breached SLA.
     */
    private function autoEscalateComplaints(int $schoolId): void
    {
        $openComplaints = Complaint::where('school_id', $schoolId)
            ->whereIn('status', ['Open', 'In Progress'])
            ->where('sla_breached', false)
            ->get();

        foreach ($openComplaints as $complaint) {
            $hoursOpen = Carbon::parse($complaint->created_at)->diffInHours(now());
            if ($hoursOpen >= $complaint->sla_hours) {
                $complaint->update([
                    'sla_breached'     => true,
                    'escalated_at'     => now(),
                    'escalation_level' => $complaint->escalation_level + 1,
                ]);
            }
        }
    }
}
