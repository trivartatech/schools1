<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\FeePayment;
use App\Models\Student;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\HostelBed;
use App\Models\TransportStudentAllocation;
use App\Models\TransportVehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AiInsightsController extends Controller
{
    public function index()
    {
        $school = app('current_school');
        $today  = now()->toDateString();
        $cached = Cache::get("school_ai_insights_{$school->id}_{$today}");

        return Inertia::render('School/Ai/Insights', [
            'initialInsights'    => $cached['insights']     ?? [],
            'initialSnapshot'    => $cached['snapshot']     ?? null,
            'initialGeneratedAt' => $cached['generated_at'] ?? null,
        ]);
    }

    // ── Collect live school data snapshot ──────────────────────────────────
    private function getSchoolData(): array
    {
        $school     = app('current_school');
        $year       = app('current_academic_year');
        $today      = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        // ── Students ──────────────────────────────────────────────────────
        $totalStudents   = Student::where('school_id', $school->id)->where('status', 'active')->count();
        $newThisMonth    = Student::where('school_id', $school->id)
            ->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();
        $genderBreakdown = Student::where('school_id', $school->id)->where('status', 'active')
            ->select('gender', DB::raw('count(*) as total'))
            ->groupBy('gender')->pluck('total', 'gender')->toArray();

        // ── Student Attendance ────────────────────────────────────────────
        $attToday = Attendance::where('school_id', $school->id)
            ->where('academic_year_id', $year->id)->where('date', $today)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->pluck('total', 'status')->toArray();

        $presentToday = ($attToday['present'] ?? 0) + ($attToday['late'] ?? 0) + ($attToday['half_day'] ?? 0);
        $absentToday  = $attToday['absent'] ?? 0;
        $totalMarked  = array_sum($attToday);
        $attPct       = $totalMarked > 0 ? round($presentToday / $totalMarked * 100, 1) : null;

        $thirtyDaysAgo = now()->subDays(30)->toDateString();
        $lowAttendance = Attendance::where('attendances.school_id', $school->id)
            ->where('attendances.academic_year_id', $year->id)
            ->where('attendances.date', '>=', $thirtyDaysAgo)
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

        // ── Fees ──────────────────────────────────────────────────────────
        $feeToday = FeePayment::where('school_id', $school->id)
            ->where('academic_year_id', $year->id)
            ->whereDate('payment_date', $today)
            ->where('amount_paid', '>', 0)
            ->sum('amount_paid');

        $feeMonth = FeePayment::where('school_id', $school->id)
            ->where('academic_year_id', $year->id)
            ->whereDate('payment_date', '>=', $monthStart)
            ->where('amount_paid', '>', 0)
            ->sum('amount_paid');

        $schoolPending   = app(\App\Services\FeeService::class)->getSchoolPendingFees($school->id, $year->id);
        $pendingFee      = $schoolPending['pending_fees'];
        $overdueStudents = count($schoolPending['pending_fee_students']);
        $topDue          = collect($schoolPending['pending_fee_students'])
            ->map(fn($r) => ['name' => $r['student'], 'due' => round($r['balance'], 2)])
            ->toArray();

        // ── Staff Attendance ──────────────────────────────────────────────
        $staffTotal  = Staff::where('school_id', $school->id)->count();
        $staffAtt    = StaffAttendance::where('school_id', $school->id)->where('date', $today)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->pluck('total', 'status')->toArray();
        $staffPresent = ($staffAtt['present'] ?? 0) + ($staffAtt['late'] ?? 0) + ($staffAtt['half_day'] ?? 0);
        $staffAbsent  = $staffAtt['absent'] ?? 0;
        $staffMarked  = array_sum($staffAtt);
        $staffPct     = $staffMarked > 0 ? round($staffPresent / $staffMarked * 100, 1) : null;

        // ── Exam Performance ──────────────────────────────────────────────
        $examStats = null;
        try {
            $row = DB::table('exam_marks')
                ->join('exam_schedule_subject_marks', function ($join) {
                    $join->on('exam_marks.exam_schedule_subject_id', '=', 'exam_schedule_subject_marks.exam_schedule_subject_id')
                         ->on('exam_marks.exam_assessment_item_id', '=', 'exam_schedule_subject_marks.exam_assessment_item_id');
                })
                ->where('exam_marks.school_id', $school->id)
                ->where('exam_marks.academic_year_id', $year->id)
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
        } catch (\Exception $e) {
            // Exam module may not have data yet
        }

        // ── Hostel ────────────────────────────────────────────────────────
        $hostelStats = null;
        try {
            $hostelTotal     = HostelBed::where('school_id', $school->id)->count();
            $hostelOccupied  = HostelBed::where('school_id', $school->id)->where('status', 'Occupied')->count();
            $hostelAvailable = HostelBed::where('school_id', $school->id)->where('status', 'Available')->count();
            if ($hostelTotal > 0) {
                $hostelStats = [
                    'total_beds'    => $hostelTotal,
                    'occupied'      => $hostelOccupied,
                    'available'     => $hostelAvailable,
                    'occupancy_pct' => round($hostelOccupied / $hostelTotal * 100, 1),
                ];
            }
        } catch (\Exception $e) {
            // Hostel module may not be active
        }

        // ── Transport ─────────────────────────────────────────────────────
        $transportStats = null;
        try {
            $transportStudents = TransportStudentAllocation::where('school_id', $school->id)
                ->where('status', 'active')->count();
            $activeVehicles = TransportVehicle::where('school_id', $school->id)
                ->where('status', 'active')->count();
            $expiringDocs = TransportVehicle::where('school_id', $school->id)
                ->where('status', 'active')
                ->where(function ($q) {
                    $threshold = now()->addDays(30)->toDateString();
                    $q->where('insurance_expiry', '<=', $threshold)
                      ->orWhere('fitness_expiry', '<=', $threshold)
                      ->orWhere('pollution_expiry', '<=', $threshold);
                })->count();

            if ($activeVehicles > 0 || $transportStudents > 0) {
                $transportStats = [
                    'students'        => $transportStudents,
                    'active_vehicles' => $activeVehicles,
                    'expiring_docs'   => $expiringDocs,
                ];
            }
        } catch (\Exception $e) {
            // Transport module may not be active
        }

        return [
            'school_name'   => $school->name,
            'academic_year' => $year->name ?? 'Current Year',
            'date'          => $today,
            'students' => [
                'total'          => $totalStudents,
                'new_this_month' => $newThisMonth,
                'gender'         => $genderBreakdown,
            ],
            'attendance' => [
                'present_today'           => $presentToday,
                'absent_today'            => $absentToday,
                'total_marked'            => $totalMarked,
                'percentage'              => $attPct,
                'low_attendance_students' => $lowAttendance,
            ],
            'fees' => [
                'collected_today'  => round($feeToday, 2),
                'collected_month'  => round($feeMonth, 2),
                'total_pending'    => round($pendingFee, 2),
                'overdue_students' => $overdueStudents,
                'top_due_students' => $topDue,
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
    }

    // ── Generate Smart Insights ────────────────────────────────────────────
    public function generateInsights(Request $request)
    {
        $school   = app('current_school');
        $today    = now()->toDateString();
        $cacheKey = "school_ai_insights_{$school->id}_{$today}";
        $force    = $request->boolean('force', false);

        if (!$force && Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $data     = $this->getSchoolData();
        $dataJson = json_encode($data, JSON_PRETTY_PRINT);

        $prompt = "You are an AI analyst for a school management system. Analyze this real-time school data and generate smart insights.

DATA:
{$dataJson}

Generate exactly 8 insights. Cover these categories in priority order: Attendance, Finance, Students, Staff, Exams (only if exams data is non-null), Hostel (only if hostel data is non-null), Transport (only if transport data is non-null), Operations.
For null data sections, substitute with additional Attendance/Finance/Students insights instead.

Respond ONLY with a valid JSON array, no markdown:
[
  {
    \"category\": \"Attendance\",
    \"severity\": \"warning\",
    \"icon\": \"📅\",
    \"metric\": \"87.3%\",
    \"trend\": \"down\",
    \"title\": \"Short title (max 6 words)\",
    \"insight\": \"2-3 sentences with specific numbers from the data.\",
    \"action\": \"One clear recommended action.\"
  }
]

Field rules:
- severity: \"success\" (positive), \"warning\" (needs attention), \"danger\" (urgent)
- trend: \"up\" (improving/high), \"down\" (declining/low), \"stable\" (neutral)
- icon: a single relevant emoji for the category
- metric: the single most important stat for this insight (e.g. \"87.3%\", \"₹1.2L\", \"23 students\") — use actual numbers from the data
- Be specific, always use real numbers from the data provided.";

        $response = Http::withToken(config('services.groq.key'))
            ->timeout(60)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => 'llama-3.3-70b-versatile',
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.4,
                'max_tokens'  => 3000,
            ]);

        if ($response->failed()) {
            return response()->json(['error' => $response->json('error.message') ?? 'AI unavailable.'], 503);
        }

        $raw   = $response->json('choices.0.message.content') ?? '';
        $start = strpos($raw, '[');
        $end   = strrpos($raw, ']');
        if ($start === false || $end === false) {
            return response()->json(['error' => 'AI returned an unexpected format. Please try again.'], 500);
        }
        $raw      = substr($raw, $start, $end - $start + 1);
        $insights = json_decode($raw, true);

        if (!is_array($insights)) {
            return response()->json(['error' => 'Could not parse AI response. Please try again.'], 500);
        }

        $result = [
            'insights'     => $insights,
            'snapshot'     => $data,
            'generated_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $result, now()->addHours(2));

        return response()->json($result);
    }

    // ── Ask Your Data ──────────────────────────────────────────────────────
    public function queryData(Request $request)
    {
        $request->validate([
            'question'      => 'required|string|max:500',
            'history'       => 'nullable|array|max:10',
            'history.*.q'   => 'string|max:500',
            'history.*.a'   => 'string|max:1000',
        ]);

        $data     = $this->getSchoolData();
        $dataJson = json_encode($data, JSON_PRETTY_PRINT);

        // Build conversation history block (last 5 turns max)
        $historyStr = '';
        if (!empty($request->history)) {
            $historyStr = "\nCONVERSATION HISTORY (use for context on follow-up questions):\n";
            foreach (array_slice($request->history, -5) as $turn) {
                $historyStr .= "User: {$turn['q']}\nAI: {$turn['a']}\n\n";
            }
        }

        $prompt = "You are a data assistant for a school ERP system.

AVAILABLE REAL-TIME DATA:
{$dataJson}
{$historyStr}
USER QUESTION: \"{$request->question}\"

Answer using ONLY the data provided. Be concise and direct.
- Use specific numbers from the data
- If the answer isn't in the data, say what IS available
- Use bullet points for lists
- Keep the answer under 150 words

Also suggest exactly 3 short follow-up questions the user might want to ask next.

Respond ONLY with valid JSON (no markdown):
{\"answer\": \"your answer here\", \"follow_ups\": [\"Question 1?\", \"Question 2?\", \"Question 3?\"]}";

        $response = Http::withToken(config('services.groq.key'))
            ->timeout(30)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => 'llama-3.3-70b-versatile',
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.3,
                'max_tokens'  => 600,
            ]);

        if ($response->failed()) {
            return response()->json(['error' => $response->json('error.message') ?? 'AI unavailable.'], 503);
        }

        $raw    = $response->json('choices.0.message.content') ?? '';
        $start  = strpos($raw, '{');
        $end    = strrpos($raw, '}');
        $parsed = null;

        if ($start !== false && $end !== false) {
            $parsed = json_decode(substr($raw, $start, $end - $start + 1), true);
        }

        return response()->json([
            'answer'     => $parsed['answer']     ?? $raw,
            'follow_ups' => $parsed['follow_ups'] ?? [],
            'snapshot'   => $data,
        ]);
    }
}
