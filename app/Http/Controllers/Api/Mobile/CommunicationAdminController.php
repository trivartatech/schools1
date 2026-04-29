<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\CommunicationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mobile Communication Hub admin endpoints (read-only).
 *
 * Mirrors the web CommunicationDashboardController::logs() and ::analytics()
 * methods but returns JSON shaped for the mobile UI. Logs are written by
 * NotificationService whenever a message goes out, so this controller
 * never writes — it only reads + aggregates.
 */
class CommunicationAdminController extends Controller
{
    private const CHANNELS = ['sms', 'whatsapp', 'voice', 'email', 'push'];
    private const STATUSES = ['sent', 'failed', 'pending'];

    private function assertCommunicationAdmin(Request $request): void
    {
        $user = $request->user();
        $type = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        if (!in_array($type, ['admin', 'school_admin', 'principal', 'super_admin'], true)) {
            abort(response()->json(['error' => 'Unauthorized.'], 403));
        }
    }

    private function logPayload(CommunicationLog $log): array
    {
        // Pick out a short error reason from provider_response when status=failed.
        $reason = null;
        if ($log->status === 'failed' && is_array($log->provider_response)) {
            $reason = $log->provider_response['error']
                   ?? $log->provider_response['message']
                   ?? $log->provider_response['errors'][0]
                   ?? null;
            if (is_array($reason)) {
                $reason = $reason['message'] ?? json_encode($reason);
            }
        }

        return [
            'id'         => $log->id,
            'type'       => $log->type,
            'provider'   => $log->provider,
            'to'         => $log->to,
            'message'    => $log->message,
            'status'     => $log->status,
            'sender'     => $log->user ? [
                'id'   => $log->user->id,
                'name' => $log->user->name,
            ] : null,
            'failure_reason' => is_string($reason) ? $reason : null,
            'created_at'     => $log->created_at?->toIso8601String(),
        ];
    }

    /**
     * GET /mobile/communication/logs
     *
     * Query params (all optional):
     *   type      sms|whatsapp|voice|email|push
     *   status    sent|failed|pending
     *   search    substring on `to` or `message`
     *   from      YYYY-MM-DD (default: 7 days ago)
     *   to        YYYY-MM-DD (default: today)
     *   per_page  5..100 (default 30)
     *   page      >=1
     */
    public function logs(Request $request): JsonResponse
    {
        $this->assertCommunicationAdmin($request);
        $schoolId = app('current_school_id');

        $base = CommunicationLog::where('school_id', $schoolId)
            ->with('user:id,name');

        if (in_array($request->input('type'),   self::CHANNELS, true)) $base->where('type',   $request->input('type'));
        if (in_array($request->input('status'), self::STATUSES, true)) $base->where('status', $request->input('status'));

        if ($search = trim((string) $request->input('search'))) {
            $base->where(function ($q) use ($search) {
                $q->where('to',      'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        // Date range filter — default last 7 days, capped at 90.
        try {
            $to   = $request->input('to')   ? Carbon::parse($request->input('to'))->endOfDay()     : Carbon::now()->endOfDay();
            $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : Carbon::now()->subDays(6)->startOfDay();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid date range.'], 422);
        }
        if ($from->gt($to)) [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        if ($from->diffInDays($to) > 90) $from = $to->copy()->subDays(90)->startOfDay();
        $base->whereBetween('created_at', [$from, $to]);

        // Stats over the same filter set
        $stats = (clone $base)
            ->selectRaw("
                COUNT(*) as total_count,
                SUM(CASE WHEN status='sent'    THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status='failed'  THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count
            ")
            ->first();

        $perPage   = max(5, min(100, (int) $request->input('per_page', 30)));
        $paginated = $base->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data'         => collect($paginated->items())->map(fn($l) => $this->logPayload($l))->values(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'summary' => [
                'total'    => (int) ($stats->total_count   ?? 0),
                'sent'     => (int) ($stats->sent_count    ?? 0),
                'failed'   => (int) ($stats->failed_count  ?? 0),
                'pending'  => (int) ($stats->pending_count ?? 0),
                'rate'     => ($stats->total_count ?? 0) > 0
                    ? round(($stats->sent_count / $stats->total_count) * 100, 1)
                    : null,
            ],
            'applied' => [
                'type'   => $request->input('type'),
                'status' => $request->input('status'),
                'search' => $request->input('search'),
                'from'   => $from->toDateString(),
                'to'     => $to->toDateString(),
            ],
        ]);
    }

    /**
     * GET /mobile/communication/analytics
     *
     * Query params:
     *   days   1..30 (default 7) — mirrors the web endpoint shape but caps
     *          at 30 to keep mobile payloads small.
     *
     * Returns: daily trend, per-channel breakdown, top failure channels,
     * and a top-line summary so the mobile dashboard renders in one call.
     */
    public function analytics(Request $request): JsonResponse
    {
        $this->assertCommunicationAdmin($request);
        $schoolId = app('current_school_id');

        $days = max(1, min(30, (int) $request->input('days', 7)));
        $from = Carbon::now()->subDays($days - 1)->startOfDay();
        $to   = Carbon::now()->endOfDay();

        $base = CommunicationLog::where('school_id', $schoolId)
            ->whereBetween('created_at', [$from, $to]);

        // Daily trend — date-wise totals
        $daily = (clone $base)
            ->selectRaw("DATE(created_at) as d, status, COUNT(*) as cnt")
            ->groupBy('d', 'status')
            ->orderBy('d')
            ->get()
            ->groupBy('d');

        // Build a complete day list so days with zero messages still appear.
        $trend = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $rows = $daily->get($key, collect());
            $sent    = (int) ($rows->where('status', 'sent')->first()?->cnt    ?? 0);
            $failed  = (int) ($rows->where('status', 'failed')->first()?->cnt  ?? 0);
            $pending = (int) ($rows->where('status', 'pending')->first()?->cnt ?? 0);
            $trend[] = [
                'date'      => $key,
                'total'     => $sent + $failed + $pending,
                'delivered' => $sent,
                'failed'    => $failed,
                'pending'   => $pending,
            ];
            $cursor->addDay();
        }

        // Channel × status breakdown
        $channelRows = (clone $base)
            ->selectRaw("type, status, COUNT(*) as cnt")
            ->groupBy('type', 'status')
            ->get();

        $channels = [];
        foreach (self::CHANNELS as $ch) {
            $rows = $channelRows->where('type', $ch);
            $sent   = (int) ($rows->where('status', 'sent')->first()?->cnt    ?? 0);
            $failed = (int) ($rows->where('status', 'failed')->first()?->cnt  ?? 0);
            $pend   = (int) ($rows->where('status', 'pending')->first()?->cnt ?? 0);
            $total  = $sent + $failed + $pend;
            if ($total === 0) continue;
            $channels[] = [
                'type'      => $ch,
                'total'     => $total,
                'delivered' => $sent,
                'failed'    => $failed,
                'pending'   => $pend,
                'rate'      => $total > 0 ? round(($sent / $total) * 100, 1) : null,
            ];
        }
        usort($channels, fn($a, $b) => $b['total'] <=> $a['total']);

        // Top failure reasons (extract from provider_response JSON; cheap because
        // we limit to recent failures only)
        $recentFailures = (clone $base)->where('status', 'failed')
            ->latest('id')->limit(50)
            ->get(['type', 'provider_response']);

        $reasonCounts = [];
        foreach ($recentFailures as $row) {
            $resp = $row->provider_response;
            if (!is_array($resp)) continue;
            $reason = $resp['error'] ?? $resp['message'] ?? $resp['errors'][0] ?? null;
            if (is_array($reason)) $reason = $reason['message'] ?? json_encode($reason);
            $reason = is_string($reason) ? trim($reason) : null;
            if (!$reason) continue;
            $key = strtolower(substr($reason, 0, 80));
            if (!isset($reasonCounts[$key])) {
                $reasonCounts[$key] = ['reason' => $reason, 'count' => 0, 'channels' => []];
            }
            $reasonCounts[$key]['count']++;
            $reasonCounts[$key]['channels'][$row->type] = true;
        }
        $topFailures = array_values(array_map(fn($r) => [
            'reason'   => $r['reason'],
            'count'    => $r['count'],
            'channels' => array_keys($r['channels']),
        ], $reasonCounts));
        usort($topFailures, fn($a, $b) => $b['count'] <=> $a['count']);
        $topFailures = array_slice($topFailures, 0, 5);

        // Summary
        $sent    = collect($trend)->sum('delivered');
        $failed  = collect($trend)->sum('failed');
        $pending = collect($trend)->sum('pending');
        $total   = $sent + $failed + $pending;

        return response()->json([
            'data' => [
                'summary' => [
                    'total'     => $total,
                    'delivered' => $sent,
                    'failed'    => $failed,
                    'pending'   => $pending,
                    'rate'      => $total > 0 ? round(($sent / $total) * 100, 1) : null,
                ],
                'daily_trend'  => $trend,
                'channels'     => $channels,
                'top_failures' => $topFailures,
            ],
            'applied' => [
                'days' => $days,
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
        ]);
    }
}
