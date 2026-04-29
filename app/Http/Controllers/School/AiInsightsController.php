<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\AiInsight;
use App\Models\Attendance;
use App\Models\FeePayment;
use App\Models\HostelBed;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\TransportStudentAllocation;
use App\Models\TransportVehicle;
use App\Services\AnalyticsService;
use App\Services\GroqClient;
use App\Utils\AiJsonParser;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AiInsightsController extends Controller
{
    private const ALLOWED_DRILL_LINKS = [
        '/school/attendance/report',
        '/school/finance/due-report',
        '/school/finance/day-book',
        '/school/finance/reports',
        '/school/transport/reports/fee-defaulters',
        '/school/transport/vehicles',
        '/school/transport/routes',
        '/school/students',
        '/school/registrations',
        '/school/staff-attendance/report',
        '/school/leaves',
        '/school/exam-results',
        '/school/report-cards',
        '/school/hostel/allocations',
        '/school/expenses',
        '/school/payroll',
    ];

    private const CACHE_TTL_MINUTES = 30;

    public function __construct(
        private GroqClient $groq,
        private AnalyticsService $analytics
    ) {}

    public function index(Request $request)
    {
        $school   = app('current_school');
        $today    = now()->toDateString();

        // Try cache first; on miss, look up the latest persisted insight for today.
        $cacheKey = $this->cacheKey($school->id, $today, $today, false);
        $cached   = Cache::get($cacheKey);

        if (!$cached) {
            $latest = AiInsight::where('school_id', $school->id)
                ->where('snapshot_date', $today)
                ->latest('generated_at')
                ->first();
            if ($latest) {
                $cached = [
                    'insights'     => $latest->insights_json,
                    'snapshot'     => $latest->snapshot_json,
                    'generated_at' => $latest->generated_at?->toISOString(),
                ];
            }
        }

        return Inertia::render('School/Ai/Insights', [
            'initialInsights'    => $cached['insights']     ?? [],
            'initialSnapshot'    => $cached['snapshot']     ?? null,
            'initialGeneratedAt' => $cached['generated_at'] ?? null,
            'allowedDrillLinks'  => self::ALLOWED_DRILL_LINKS,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Snapshot collection
    // ──────────────────────────────────────────────────────────────────

    private function getSchoolData(?Carbon $from = null, ?Carbon $to = null, bool $withComparison = false): array
    {
        $school = app('current_school');
        $year   = app('current_academic_year');
        $sid    = $school->id;
        $yid    = $year?->id;

        $to    = $to    ?: now();
        $from  = $from  ?: now()->startOfMonth();
        $today = $to->toDateString();

        $monthStart = $from->toDateString();

        // ── Students ─────────────────────────────────────────────────
        $totalStudents   = Student::where('school_id', $sid)->where('status', 'active')->enrolledInYear($yid)->count();
        $newInRange      = Student::where('school_id', $sid)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->enrolledInYear($yid)
            ->count();
        $genderBreakdown = Student::where('school_id', $sid)->where('status', 'active')->enrolledInYear($yid)
            ->select('gender', DB::raw('count(*) as total'))
            ->groupBy('gender')->pluck('total', 'gender')->toArray();

        // ── Attendance ───────────────────────────────────────────────
        $attToday = Attendance::where('school_id', $sid)
            ->when($yid, fn($q) => $q->where('academic_year_id', $yid))
            ->where('date', $today)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->pluck('total', 'status')->toArray();

        $presentToday = ($attToday['present'] ?? 0) + ($attToday['late'] ?? 0) + ($attToday['half_day'] ?? 0);
        $absentToday  = $attToday['absent'] ?? 0;
        $totalMarked  = array_sum($attToday);
        $attPct       = $totalMarked > 0 ? round($presentToday / $totalMarked * 100, 1) : null;

        $thirtyDaysAgo = $to->copy()->subDays(30)->toDateString();
        $lowAttendance = Attendance::where('attendances.school_id', $sid)
            ->when($yid, fn($q) => $q->where('attendances.academic_year_id', $yid))
            ->where('attendances.date', '>=', $thirtyDaysAgo)
            ->where('attendances.date', '<=', $today)
            ->join('students', 'students.id', '=', 'attendances.student_id')
            ->select(
                'attendances.student_id',
                DB::raw("(students.first_name || ' ' || students.last_name) as name"),
                DB::raw('COUNT(*) as total_days'),
                DB::raw("SUM(CASE WHEN attendances.status IN ('present','late','half_day') THEN 1 ELSE 0 END) as present_days")
            )
            ->groupBy('attendances.student_id', 'students.first_name', 'students.last_name')
            ->havingRaw('COUNT(*) > 0')
            ->havingRaw("SUM(CASE WHEN attendances.status IN ('present','late','half_day') THEN 1 ELSE 0 END) * 100.0 / COUNT(*) < 75")
            ->orderByRaw("SUM(CASE WHEN attendances.status IN ('present','late','half_day') THEN 1 ELSE 0 END) * 100.0 / COUNT(*) ASC")
            ->limit(10)->get()
            ->map(fn($r) => [
                'name'       => $r->name,
                'percentage' => $r->total_days > 0 ? round($r->present_days / $r->total_days * 100, 1) : 0,
            ])->toArray();

        // ── Fees ─────────────────────────────────────────────────────
        $feeToday = FeePayment::where('school_id', $sid)
            ->when($yid, fn($q) => $q->where('academic_year_id', $yid))
            ->whereDate('payment_date', $today)
            ->where('amount_paid', '>', 0)
            ->sum('amount_paid');

        $feeRange = FeePayment::where('school_id', $sid)
            ->when($yid, fn($q) => $q->where('academic_year_id', $yid))
            ->whereBetween('payment_date', [$monthStart, $today])
            ->where('amount_paid', '>', 0)
            ->sum('amount_paid');

        $schoolPending   = app(\App\Services\FeeService::class)->getSchoolPendingFees($sid, $yid);
        $pendingFee      = $schoolPending['pending_fees'];
        $overdueStudents = count($schoolPending['pending_fee_students']);
        $topDue          = collect($schoolPending['pending_fee_students'])
            ->sortByDesc('balance')
            ->take(10)
            ->map(fn($r) => ['name' => $r['student'], 'due' => round($r['balance'], 2)])
            ->values()
            ->toArray();

        // ── Staff Attendance ─────────────────────────────────────────
        $staffTotal  = Staff::where('school_id', $sid)->count();
        $staffAtt    = StaffAttendance::where('school_id', $sid)->where('date', $today)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->pluck('total', 'status')->toArray();
        $staffPresent = ($staffAtt['present'] ?? 0) + ($staffAtt['late'] ?? 0) + ($staffAtt['half_day'] ?? 0);
        $staffAbsent  = $staffAtt['absent'] ?? 0;
        $staffMarked  = array_sum($staffAtt);
        $staffPct     = $staffMarked > 0 ? round($staffPresent / $staffMarked * 100, 1) : null;

        // ── Exam Performance ─────────────────────────────────────────
        $examStats = null;
        try {
            $row = DB::table('exam_marks')
                ->join('exam_schedule_subject_marks', function ($join) {
                    $join->on('exam_marks.exam_schedule_subject_id', '=', 'exam_schedule_subject_marks.exam_schedule_subject_id')
                         ->on('exam_marks.exam_assessment_item_id',  '=', 'exam_schedule_subject_marks.exam_assessment_item_id');
                })
                ->where('exam_marks.school_id', $sid)
                ->when($yid, fn($q) => $q->where('exam_marks.academic_year_id', $yid))
                ->where('exam_marks.is_absent', false)
                ->whereNotNull('exam_marks.marks_obtained')
                ->where('exam_schedule_subject_marks.max_marks', '>', 0)
                ->selectRaw('
                    COUNT(*) as total_entries,
                    ROUND(AVG(exam_marks.marks_obtained * 100.0 / exam_schedule_subject_marks.max_marks), 1) as avg_percentage,
                    SUM(CASE WHEN exam_marks.marks_obtained >= exam_schedule_subject_marks.passing_marks THEN 1 ELSE 0 END) as passed
                ')
                ->first();

            if ($row && $row->total_entries > 0) {
                $examStats = [
                    'total_entries'  => $row->total_entries,
                    'avg_percentage' => $row->avg_percentage,
                    'pass_rate'      => round($row->passed / $row->total_entries * 100, 1),
                ];
            }
        } catch (\Throwable $e) {
            // Exam module may not have data yet
        }

        // ── Hostel ───────────────────────────────────────────────────
        $hostelStats = null;
        try {
            $hostelTotal     = HostelBed::where('school_id', $sid)->count();
            $hostelOccupied  = HostelBed::where('school_id', $sid)->where('status', 'Occupied')->count();
            $hostelAvailable = HostelBed::where('school_id', $sid)->where('status', 'Available')->count();
            if ($hostelTotal > 0) {
                $hostelStats = [
                    'total_beds'    => $hostelTotal,
                    'occupied'      => $hostelOccupied,
                    'available'     => $hostelAvailable,
                    'occupancy_pct' => round($hostelOccupied / $hostelTotal * 100, 1),
                ];
            }
        } catch (\Throwable $e) {
            // Hostel module may not be active
        }

        // ── Transport ────────────────────────────────────────────────
        $transportStats = null;
        try {
            $transportStudents = TransportStudentAllocation::where('school_id', $sid)
                ->where('status', 'active')->count();
            $activeVehicles = TransportVehicle::where('school_id', $sid)
                ->where('status', 'active')->count();
            $expiringDocs = TransportVehicle::where('school_id', $sid)
                ->where('status', 'active')
                ->where(function ($q) {
                    $threshold = now()->addDays(30)->toDateString();
                    $q->where('insurance_expiry', '<=', $threshold)
                      ->orWhere('fitness_expiry',  '<=', $threshold)
                      ->orWhere('pollution_expiry','<=', $threshold);
                })->count();

            if ($activeVehicles > 0 || $transportStudents > 0) {
                $transportStats = [
                    'students'        => $transportStudents,
                    'active_vehicles' => $activeVehicles,
                    'expiring_docs'   => $expiringDocs,
                ];
            }
        } catch (\Throwable $e) {
            // Transport module may not be active
        }

        $data = [
            'school_name'   => $school->name,
            'academic_year' => $year->name ?? 'Current Year',
            'date'          => $today,
            'range_from'    => $from->toDateString(),
            'range_to'      => $to->toDateString(),
            'students' => [
                'total'        => $totalStudents,
                'new_in_range' => $newInRange,
                'gender'       => $genderBreakdown,
            ],
            'attendance' => [
                'present_today'           => $presentToday,
                'absent_today'            => $absentToday,
                'total_marked'            => $totalMarked,
                'percentage'              => $attPct,
                'low_attendance_students' => $lowAttendance,
            ],
            'fees' => [
                'collected_today'   => round($feeToday, 2),
                'collected_in_range'=> round($feeRange, 2),
                'total_pending'     => round($pendingFee, 2),
                'overdue_students'  => $overdueStudents,
                'top_due_students'  => $topDue,
            ],
            'staff' => [
                'total'          => $staffTotal,
                'present_today'  => $staffPresent,
                'absent_today'   => $staffAbsent,
                'marked_today'   => $staffMarked,
                'attendance_pct' => $staffPct,
            ],
            'exams'     => $examStats,
            'hostel'    => $hostelStats,
            'transport' => $transportStats,
        ];

        if ($withComparison) {
            $data['comparison'] = $this->buildComparison($from, $to);
        }

        return $data;
    }

    private function buildComparison(Carbon $from, Carbon $to): array
    {
        $school = app('current_school');
        $year   = app('current_academic_year');
        $sid    = $school->id;
        $yid    = $year?->id;

        $rangeDays    = max(1, $from->diffInDays($to) + 1);
        $previousFrom = $from->copy()->subDays($rangeDays);
        $previousTo   = $from->copy()->subDay();

        // Attendance % over the previous range
        $prevAttRow = Attendance::where('school_id', $sid)
            ->when($yid, fn($q) => $q->where('academic_year_id', $yid))
            ->whereBetween('date', [$previousFrom->toDateString(), $previousTo->toDateString()])
            ->selectRaw("
                SUM(CASE WHEN status IN ('present','late','half_day') THEN 1 ELSE 0 END) as present,
                COUNT(*) as marked
            ")->first();
        $prevAttPct = ($prevAttRow && $prevAttRow->marked > 0)
            ? round($prevAttRow->present / $prevAttRow->marked * 100, 1)
            : null;

        // Fees collected over the previous range
        $prevFees = FeePayment::where('school_id', $sid)
            ->when($yid, fn($q) => $q->where('academic_year_id', $yid))
            ->whereBetween('payment_date', [$previousFrom->toDateString(), $previousTo->toDateString()])
            ->where('amount_paid', '>', 0)
            ->sum('amount_paid');

        // New students in previous range
        $prevNewStudents = Student::where('school_id', $sid)
            ->whereBetween('created_at', [$previousFrom->copy()->startOfDay(), $previousTo->copy()->endOfDay()])
            ->enrolledInYear($yid)
            ->count();

        return [
            'range_from'     => $previousFrom->toDateString(),
            'range_to'       => $previousTo->toDateString(),
            'attendance_pct' => $prevAttPct,
            'fees_collected' => round((float) $prevFees, 2),
            'new_students'   => $prevNewStudents,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Generate insights
    // ──────────────────────────────────────────────────────────────────

    public function generateInsights(Request $request)
    {
        $request->validate([
            'force'      => 'nullable|boolean',
            'from'       => 'nullable|date',
            'to'         => 'nullable|date|after_or_equal:from',
            'compare'    => 'nullable|boolean',
        ]);

        $school  = app('current_school');
        $force   = $request->boolean('force', false);
        $compare = $request->boolean('compare', false);

        [$from, $to] = $this->resolveRange($request);

        $cacheKey = $this->cacheKey($school->id, $from->toDateString(), $to->toDateString(), $compare);
        if (!$force && Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $data = $this->getSchoolData($from, $to, $compare);

        $prompt = $this->buildInsightsPrompt($data, $compare);

        $resp = $this->groq->complete($prompt, 'analyst');
        if (!$resp['ok']) {
            return response()->json(['error' => $resp['error']], $resp['status'] ?? 503);
        }

        $insights = AiJsonParser::array($resp['content']);
        if (!is_array($insights)) {
            Log::warning('AI insights JSON parse failed', ['raw' => substr($resp['content'], 0, 500)]);
            return response()->json(['error' => 'Could not parse AI response. Please try again.'], 500);
        }

        $insights = $this->sanitizeInsights($insights);

        $result = [
            'insights'     => $insights,
            'snapshot'     => $data,
            'generated_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));

        AiInsight::create([
            'school_id'        => $school->id,
            'academic_year_id' => optional(app('current_academic_year'))->id,
            'snapshot_date'    => $to->toDateString(),
            'range_from'       => $from->toDateString(),
            'range_to'         => $to->toDateString(),
            'snapshot_json'    => $data,
            'insights_json'    => $insights,
            'generated_at'     => now(),
        ]);

        return response()->json($result);
    }

    private function buildInsightsPrompt(array $data, bool $withComparison): string
    {
        $dataJson      = json_encode($data, JSON_PRETTY_PRINT);
        $allowedLinks  = json_encode(self::ALLOWED_DRILL_LINKS);
        $comparisonNote = $withComparison
            ? "\nThe data includes a `comparison` block with the previous equivalent period. When relevant, mention deltas (e.g. \"up 4% from last week\") and set `trend` to up/down/stable accordingly."
            : '';

        return <<<PROMPT
You are an AI analyst for a school management system. Analyze this real-time school data and generate smart insights.

DATA:
{$dataJson}

Generate exactly 8 insights. Cover these categories in priority order: Attendance, Finance, Students, Staff, Exams (only if exams data is non-null), Hostel (only if hostel data is non-null), Transport (only if transport data is non-null), Operations.
For null data sections, substitute with additional Attendance/Finance/Students insights instead.{$comparisonNote}

Respond ONLY with a valid JSON array, no markdown:
[
  {
    "category": "Attendance",
    "severity": "warning",
    "icon": "📅",
    "metric": "87.3%",
    "trend": "down",
    "title": "Short title (max 6 words)",
    "insight": "2-3 sentences with specific numbers from the data.",
    "action": "One clear recommended action.",
    "link": "/school/attendance/report"
  }
]

Field rules:
- severity: "success" (positive), "warning" (needs attention), "danger" (urgent)
- trend: "up" (improving/high), "down" (declining/low), "stable" (neutral)
- icon: a single relevant emoji for the category
- metric: the single most important stat (e.g. "87.3%", "₹1.2L", "23 students") — use REAL numbers from data
- link: optional drill-down route. MUST be one of: {$allowedLinks}. Omit the field if no exact match.
- Be specific, always use real numbers.
PROMPT;
    }

    /**
     * Sanitize LLM output: drop unknown drill-link routes, ensure required fields.
     */
    private function sanitizeInsights(array $insights): array
    {
        return array_map(function ($ins) {
            if (!is_array($ins)) return null;

            $clean = [
                'category' => (string) ($ins['category'] ?? ''),
                'severity' => in_array($ins['severity'] ?? '', ['success', 'warning', 'danger'], true) ? $ins['severity'] : 'warning',
                'icon'     => (string) ($ins['icon']    ?? '📊'),
                'metric'   => (string) ($ins['metric']  ?? ''),
                'trend'    => in_array($ins['trend'] ?? '', ['up', 'down', 'stable'], true) ? $ins['trend'] : 'stable',
                'title'    => (string) ($ins['title']   ?? ''),
                'insight'  => (string) ($ins['insight'] ?? ''),
                'action'   => (string) ($ins['action']  ?? ''),
            ];

            $link = (string) ($ins['link'] ?? '');
            if ($link !== '' && in_array($link, self::ALLOWED_DRILL_LINKS, true)) {
                $clean['link'] = $link;
            }

            return $clean;
        }, array_filter($insights, fn($i) => is_array($i)));
    }

    // ──────────────────────────────────────────────────────────────────
    // Q&A: Ask Your Data
    // ──────────────────────────────────────────────────────────────────

    public function queryData(Request $request)
    {
        $request->validate([
            'question'    => 'required|string|max:500',
            'history'     => 'nullable|array|max:5',
            'history.*.q' => 'string|max:500',
            'history.*.a' => 'string|max:1000',
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
        ]);

        [$from, $to] = $this->resolveRange($request);
        $data        = $this->getSchoolData($from, $to);

        $prompt = $this->buildQueryPrompt($data, $request->question, $request->history ?? []);
        $resp   = $this->groq->complete($prompt, 'fast');

        if (!$resp['ok']) {
            return response()->json(['error' => $resp['error']], $resp['status'] ?? 503);
        }

        $parsed = AiJsonParser::object($resp['content']);

        return response()->json([
            'answer'     => $parsed['answer']     ?? $resp['content'],
            'follow_ups' => $parsed['follow_ups'] ?? [],
        ]);
    }

    /**
     * Streaming variant — emits SSE deltas for the answer text.
     */
    public function queryDataStream(Request $request)
    {
        $request->validate([
            'question'    => 'required|string|max:500',
            'history'     => 'nullable|array|max:5',
            'history.*.q' => 'string|max:500',
            'history.*.a' => 'string|max:1000',
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
        ]);

        [$from, $to] = $this->resolveRange($request);
        $data        = $this->getSchoolData($from, $to);

        $prompt = $this->buildQueryPrompt($data, $request->question, $request->history ?? [], plainText: true);

        return response()->stream(function () use ($prompt) {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            while (ob_get_level()) ob_end_flush();

            $this->groq->streamLive(
                [['role' => 'user', 'content' => $prompt]],
                'fast',
                function (string $delta) {
                    echo "event: token\n";
                    echo 'data: ' . json_encode(['t' => $delta]) . "\n\n";
                    @ob_flush();
                    @flush();
                }
            );

            echo "event: done\n";
            echo "data: {}\n\n";
            @ob_flush();
            @flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    private function buildQueryPrompt(array $data, string $question, array $history, bool $plainText = false): string
    {
        $dataJson = json_encode($data, JSON_PRETTY_PRINT);

        $historyStr = '';
        if (!empty($history)) {
            $historyStr = "\nCONVERSATION HISTORY (use for context on follow-up questions):\n";
            foreach (array_slice($history, -5) as $turn) {
                $q = $turn['q'] ?? '';
                $a = $turn['a'] ?? '';
                $historyStr .= "User: {$q}\nAI: {$a}\n\n";
            }
        }

        if ($plainText) {
            return <<<PROMPT
You are a data assistant for a school ERP system.

AVAILABLE REAL-TIME DATA:
{$dataJson}
{$historyStr}
USER QUESTION: "{$question}"

Answer using ONLY the data provided. Be concise and direct.
- Use specific numbers from the data
- If the answer isn't in the data, say what IS available
- Use bullet points for lists
- Keep under 150 words
PROMPT;
        }

        return <<<PROMPT
You are a data assistant for a school ERP system.

AVAILABLE REAL-TIME DATA:
{$dataJson}
{$historyStr}
USER QUESTION: "{$question}"

Answer using ONLY the data provided. Be concise and direct.
- Use specific numbers from the data
- If the answer isn't in the data, say what IS available
- Use bullet points for lists
- Keep the answer under 150 words

Also suggest exactly 3 short follow-up questions the user might want to ask next.

Respond ONLY with valid JSON (no markdown):
{"answer": "your answer here", "follow_ups": ["Question 1?", "Question 2?", "Question 3?"]}
PROMPT;
    }

    // ──────────────────────────────────────────────────────────────────
    // Charts API + Explain trend
    // ──────────────────────────────────────────────────────────────────

    public function explainChart(Request $request)
    {
        $request->validate([
            'chart'  => 'required|string|in:attendance_trend,fee_collection,enrollment_by_class,exam_performance,top_defaulters',
            'series' => 'required|array|min:1|max:200',
        ]);

        $chart  = $request->string('chart');
        $series = json_encode($request->input('series'));

        $prompt = <<<PROMPT
You are a data analyst. Given the following data series for the chart "{$chart}", write a short, friendly explanation (2-3 sentences) describing the key trend, any outlier, and one actionable suggestion. Use specific numbers from the data. No markdown — plain text only.

DATA:
{$series}
PROMPT;

        return response()->stream(function () use ($prompt) {
            @ini_set('output_buffering', 'off');
            while (ob_get_level()) ob_end_flush();

            $this->groq->streamLive(
                [['role' => 'user', 'content' => $prompt]],
                'fast',
                function (string $delta) {
                    echo "event: token\n";
                    echo 'data: ' . json_encode(['t' => $delta]) . "\n\n";
                    @ob_flush();
                    @flush();
                }
            );

            echo "event: done\n";
            echo "data: {}\n\n";
            @ob_flush();
            @flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Charts data endpoint (server-rendered for the page)
    // ──────────────────────────────────────────────────────────────────

    public function charts(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        [$from, $to] = $this->resolveRange($request);

        $school = app('current_school');
        $yid    = optional(app('current_academic_year'))->id;

        return response()->json([
            'attendance_trend'    => $this->analytics->attendanceTrend($school->id, $yid, $from, $to),
            'fee_collection'      => $this->analytics->feeCollectionTrend($school->id, $yid),
            'enrollment_by_class' => $this->analytics->enrollmentByClass($school->id, $yid),
            'top_defaulters'      => $this->analytics->topFeeDefaulters($school->id, $yid, 10),
            'exam_performance'    => $this->analytics->examPerformance($school->id, $yid),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Export PDF + Excel
    // ──────────────────────────────────────────────────────────────────

    public function exportPdf(Request $request)
    {
        $request->validate([
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
            'chartImages' => 'nullable|array',
            'chartImages.*'=> 'string',
        ]);

        [$from, $to] = $this->resolveRange($request);
        $school = app('current_school');

        $latest = AiInsight::where('school_id', $school->id)
            ->where('range_from', $from->toDateString())
            ->where('range_to',   $to->toDateString())
            ->latest('generated_at')
            ->first();

        $snapshot = $latest?->snapshot_json ?? $this->getSchoolData($from, $to);
        $insights = $latest?->insights_json ?? [];

        $pdf = Pdf::loadView('exports.ai-insights-pdf', [
            'school'      => $school,
            'snapshot'    => $snapshot,
            'insights'    => $insights,
            'from'        => $from,
            'to'          => $to,
            'chartImages' => $request->input('chartImages', []),
            'generatedAt' => now(),
        ]);

        $filename = 'ai-insights-' . $from->toDateString() . '-to-' . $to->toDateString() . '.pdf';
        return $pdf->download($filename);
    }

    public function exportExcel(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        [$from, $to] = $this->resolveRange($request);
        $school = app('current_school');

        $latest = AiInsight::where('school_id', $school->id)
            ->where('range_from', $from->toDateString())
            ->where('range_to',   $to->toDateString())
            ->latest('generated_at')
            ->first();

        $snapshot = $latest?->snapshot_json ?? $this->getSchoolData($from, $to);
        $insights = $latest?->insights_json ?? [];

        $filename = 'ai-insights-' . $from->toDateString() . '-to-' . $to->toDateString() . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\AiInsightsExport($snapshot, $insights, $school, $from, $to),
            $filename
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    private function resolveRange(Request $request): array
    {
        $year = app('current_academic_year');

        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->startOfMonth();

        // Clamp to academic year boundaries when known
        if ($year && $year->start_date) {
            $yearStart = Carbon::parse($year->start_date)->startOfDay();
            if ($from->lt($yearStart)) $from = $yearStart;
        }
        if ($year && $year->end_date) {
            $yearEnd = Carbon::parse($year->end_date)->endOfDay();
            if ($to->gt($yearEnd)) $to = $yearEnd;
        }

        if ($from->gt($to)) {
            $from = $to->copy()->startOfDay();
        }

        return [$from, $to];
    }

    private function cacheKey(int $schoolId, string $from, string $to, bool $compare): string
    {
        $suffix = $compare ? '_cmp' : '';
        return "school_ai_insights_{$schoolId}_{$from}_{$to}{$suffix}";
    }
}
