<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\Student;
use App\Models\StudentParent;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class CommunicationDashboardController extends Controller
{
    // ── 1. Dashboard ─────────────────────────────────────────────────────

    public function index()
    {
        $schoolId = app('current_school_id');
        $school   = app('current_school');
        $settings = $school->settings ?? [];

        // Channel health
        $channels = [
            'sms'      => !empty($settings['sms']['api_key'] ?? null),
            'whatsapp' => !empty($settings['whatsapp']['api_key'] ?? null),
            'voice'    => !empty($settings['voice']['api_key'] ?? null),
            'email'    => !empty($settings['notifications_v2']['email'] ?? false),
        ];

        // Stats from logs (last 30 days)
        $since = now()->subDays(30);
        $logs  = CommunicationLog::where('school_id', $schoolId)
            ->where('created_at', '>=', $since);

        $totalSent   = (clone $logs)->count();
        $delivered   = (clone $logs)->where('status', 'sent')->count();
        $failed      = (clone $logs)->where('status', 'failed')->count();
        $pending     = (clone $logs)->where('status', 'pending')->count();

        // Channel breakdown
        $channelBreakdown = CommunicationLog::where('school_id', $schoolId)
            ->where('created_at', '>=', $since)
            ->selectRaw("type, count(*) as total, sum(case when status='sent' then 1 else 0 end) as delivered, sum(case when status='failed' then 1 else 0 end) as failed")
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        // Recent activity
        $recentLogs = CommunicationLog::where('school_id', $schoolId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Pending announcements
        $pendingAnnouncements = Announcement::where('school_id', $schoolId)
            ->where('is_broadcasted', false)
            ->whereNull('failed_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Templates count
        $templateCount = CommunicationTemplate::where('school_id', $schoolId)->where('is_active', true)->count();

        return Inertia::render('School/Communication/Dashboard', [
            'channels'             => $channels,
            'stats'                => compact('totalSent', 'delivered', 'failed', 'pending'),
            'channelBreakdown'     => $channelBreakdown,
            'recentLogs'           => $recentLogs,
            'pendingAnnouncements' => $pendingAnnouncements,
            'templateCount'        => $templateCount,
        ]);
    }

    // ── 2. Communication Logs Viewer ─────────────────────────────────────

    public function logs(Request $request)
    {
        $schoolId = app('current_school_id');
        $query = CommunicationLog::where('school_id', $schoolId)
            ->with('user:id,name');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('to', 'like', "%{$s}%")
                  ->orWhere('message', 'like', "%{$s}%");
            });
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $logs = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        return Inertia::render('School/Communication/Logs', [
            'logs'    => $logs,
            'filters' => $request->only(['type', 'status', 'search', 'from', 'to_date']),
        ]);
    }

    // ── 3. Emergency Broadcast ───────────────────────────────────────────

    public function emergencyForm()
    {
        $schoolId = app('current_school_id');
        $school   = app('current_school');
        $settings = $school->settings ?? [];

        $channels = [
            'sms'      => !empty($settings['sms']['api_key'] ?? null),
            'whatsapp' => !empty($settings['whatsapp']['api_key'] ?? null),
            'voice'    => !empty($settings['voice']['api_key'] ?? null),
        ];

        $templates = CommunicationTemplate::where('school_id', $schoolId)
            ->where('is_active', true)
            ->get(['id', 'name', 'type', 'content']);

        return Inertia::render('School/Communication/Emergency', [
            'channels'  => $channels,
            'templates' => $templates,
        ]);
    }

    public function emergencyBroadcast(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'message'  => 'required|string|max:500',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:sms,whatsapp,voice',
        ]);

        // Collect all parent + staff phone numbers
        $school = app('current_school');
        $parents = StudentParent::whereHas('students', fn($q) => $q->where('school_id', $schoolId))
            ->with('user:id,phone')
            ->get()
            ->pluck('user.phone')
            ->filter();

        $staff = \App\Models\User::whereHas('schools', fn($q) => $q->where('schools.id', $schoolId))
            ->whereNotNull('phone')
            ->pluck('phone');

        $phones = $parents->merge($staff)->unique()->values();

        $ns = app(NotificationService::class);
        $sent = 0;
        $failed = 0;

        foreach ($phones as $phone) {
            foreach ($validated['channels'] as $channel) {
                try {
                    if ($channel === 'sms') {
                        $ns->sendSms($phone, $validated['message'], null, auth()->id());
                    } elseif ($channel === 'whatsapp') {
                        // WhatsApp Business API requires template-based messages.
                        // Send as SMS fallback for emergency broadcasts (free-text).
                        $ns->sendSms($phone, '[Emergency] ' . $validated['message'], null, auth()->id());
                    } elseif ($channel === 'voice') {
                        $ns->sendVoiceCall($phone, null, $validated['message'], auth()->id());
                    }
                    $sent++;
                } catch (\Throwable $e) {
                    $failed++;
                }
            }
        }

        return redirect()->back()->with('success', "Emergency broadcast sent to {$phones->count()} recipients. Sent: {$sent}, Failed: {$failed}.");
    }

    // ── 4. Delivery Analytics ────────────────────────────────────────────

    public function analytics(Request $request)
    {
        $schoolId = app('current_school_id');
        $days = $request->input('days', 30);
        $since = now()->subDays($days);

        // Daily trend
        $dailyTrend = CommunicationLog::where('school_id', $schoolId)
            ->where('created_at', '>=', $since)
            ->selectRaw("date(created_at) as date, count(*) as total, sum(case when status='sent' then 1 else 0 end) as delivered, sum(case when status='failed' then 1 else 0 end) as failed")
            ->groupByRaw('date(created_at)')
            ->orderBy('date')
            ->get();

        // Channel-wise totals
        $channelStats = CommunicationLog::where('school_id', $schoolId)
            ->where('created_at', '>=', $since)
            ->selectRaw("type, status, count(*) as total")
            ->groupBy('type', 'status')
            ->get();

        // Top failure reasons
        $topFailures = CommunicationLog::where('school_id', $schoolId)
            ->where('created_at', '>=', $since)
            ->where('status', 'failed')
            ->selectRaw("type, count(*) as total")
            ->groupBy('type')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Summary stats
        $totalAll  = CommunicationLog::where('school_id', $schoolId)->where('created_at', '>=', $since)->count();
        $sentAll   = CommunicationLog::where('school_id', $schoolId)->where('created_at', '>=', $since)->where('status', 'sent')->count();
        $rate      = $totalAll > 0 ? round(($sentAll / $totalAll) * 100, 1) : 0;

        return Inertia::render('School/Communication/Analytics', [
            'dailyTrend'   => $dailyTrend,
            'channelStats' => $channelStats,
            'topFailures'  => $topFailures,
            'summary'      => ['total' => $totalAll, 'delivered' => $sentAll, 'rate' => $rate],
            'days'         => $days,
        ]);
    }

    // ── 5. Email Templates & Sending ─────────────────────────────────────

    public function emailTemplates()
    {
        $schoolId = app('current_school_id');

        $templates = CommunicationTemplate::where('school_id', $schoolId)
            ->where('type', 'email')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('School/Communication/EmailTemplates', [
            'templates' => $templates,
        ]);
    }

    public function storeEmailTemplate(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string|max:5000',
        ]);

        CommunicationTemplate::create([
            'school_id' => $schoolId,
            'type'      => 'email',
            'name'      => $validated['name'],
            'slug'      => \Illuminate\Support\Str::slug($validated['name']),
            'subject'   => $validated['subject'],
            'content'   => $validated['content'],
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'Email template created.');
    }

    public function sendEmail(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'template_id' => 'required|exists:communication_templates,id',
            'audience'     => 'required|in:all_parents,all_staff,class',
            'class_id'     => 'nullable|integer',
            'subject'      => 'required|string|max:255',
        ]);

        $template = CommunicationTemplate::where('school_id', $schoolId)
            ->where('id', $validated['template_id'])->firstOrFail();

        // Collect email addresses based on audience
        $emails = collect();

        if ($validated['audience'] === 'all_parents') {
            $emails = StudentParent::whereHas('students', fn($q) => $q->where('school_id', $schoolId))
                ->with('user:id,email')
                ->get()
                ->pluck('user.email')
                ->filter();
        } elseif ($validated['audience'] === 'all_staff') {
            $emails = \App\Models\User::whereHas('schools', fn($q) => $q->where('schools.id', $schoolId))
                ->whereNotNull('email')
                ->pluck('email');
        } elseif ($validated['audience'] === 'class' && $validated['class_id']) {
            $emails = Student::where('school_id', $schoolId)
                ->whereHas('academicHistories', function ($q) use ($validated) {
                    $q->where('class_id', $validated['class_id']);
                    if (app()->bound('current_academic_year_id')) {
                        $q->where('academic_year_id', app('current_academic_year_id'));
                    }
                })
                ->with('studentParent.user:id,email')
                ->get()
                ->pluck('studentParent.user.email')
                ->filter();
        }

        $emails = $emails->unique()->values();
        $sent = 0;

        foreach ($emails as $email) {
            try {
                Mail::raw($template->content, function ($msg) use ($email, $validated) {
                    $msg->to($email)->subject($validated['subject']);
                });

                CommunicationLog::create([
                    'school_id' => app('current_school_id'),
                    'user_id'   => auth()->id(),
                    'type'      => 'email',
                    'provider'  => 'smtp',
                    'to'        => $email,
                    'message'   => $validated['subject'],
                    'status'    => 'sent',
                ]);
                $sent++;
            } catch (\Throwable $e) {
                CommunicationLog::create([
                    'school_id' => app('current_school_id'),
                    'user_id'   => auth()->id(),
                    'type'      => 'email',
                    'provider'  => 'smtp',
                    'to'        => $email,
                    'message'   => $validated['subject'],
                    'status'    => 'failed',
                    'provider_response' => ['error' => $e->getMessage()],
                ]);
            }
        }

        return redirect()->back()->with('success', "Email sent to {$sent} of {$emails->count()} recipients.");
    }

    // ── 6. Scheduled Message Queue ───────────────────────────────────────

    public function scheduledQueue()
    {
        $schoolId = app('current_school_id');

        $scheduled = Announcement::where('school_id', $schoolId)
            ->with(['sender:id,name', 'template:id,name,type'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('School/Communication/ScheduledQueue', [
            'scheduled' => $scheduled,
        ]);
    }

    public function cancelScheduled(Announcement $announcement)
    {
        if ($announcement->school_id !== app('current_school_id')) {
            abort(403);
        }
        if ($announcement->is_broadcasted) {
            return redirect()->back()->with('error', 'Cannot cancel an already broadcasted announcement.');
        }

        $announcement->delete();

        return redirect()->back()->with('success', 'Scheduled announcement cancelled.');
    }

    public function retryBroadcast(Announcement $announcement)
    {
        if ($announcement->school_id !== app('current_school_id')) {
            abort(403);
        }
        if ($announcement->is_broadcasted) {
            return redirect()->back()->with('error', 'Already broadcasted.');
        }

        $announcement->update(['failed_at' => null, 'broadcast_error' => null]);

        app(\App\Services\BroadcastService::class)->broadcast($announcement);

        return redirect()->back()->with('success', 'Broadcast retried.');
    }

    // ── 7. Parent Notification History ───────────────────────────────────

    public function parentHistory()
    {
        $user = auth()->user();
        $schoolId = app('current_school_id');

        // Get parent's phone & email
        $identifiers = collect([$user->phone, $user->email])->filter()->values();

        $logs = CommunicationLog::where('school_id', $schoolId)
            ->whereIn('to', $identifiers)
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('School/Communication/ParentHistory', [
            'logs' => $logs,
        ]);
    }
}
