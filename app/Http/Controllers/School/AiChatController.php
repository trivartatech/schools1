<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Assignment;
use App\Models\Complaint;
use App\Models\CourseClass;
use App\Models\ExamSchedule;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeePayment;
use App\Models\GatePass;
use App\Models\Holiday;
use App\Models\HostelRoom;
use App\Models\HostelStudent;
use App\Models\Leave;
use App\Models\Payroll;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentLeave;
use App\Models\Subject;
use App\Models\TransferCertificate;
use App\Models\TransportRoute;
use App\Models\TransportStudentAllocation;
use App\Models\TransportVehicle;
use App\Models\VisitorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AiChatController extends Controller
{
    // ── Full live data snapshot — every module ──────────────────────────────
    private function getLiveDataSummary(): string
    {
        try {
            $school     = app('current_school');
            $year       = app('current_academic_year');
            $sid        = $school->id;
            $yid        = $year->id;
            $today      = now()->toDateString();
            $monthStart = now()->startOfMonth()->toDateString();
            $monthEnd   = now()->endOfMonth()->toDateString();

            // ── STUDENTS ──────────────────────────────────────────────────
            $totalStudents  = Student::where('school_id', $sid)->where('status', 'active')->enrolledInYear($yid)->count();
            $newThisMonth   = Student::where('school_id', $sid)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->enrolledInYear($yid)->count();
            $genderStats    = Student::where('school_id', $sid)->where('status', 'active')->enrolledInYear($yid)
                ->select('gender', DB::raw('count(*) as c'))->groupBy('gender')
                ->pluck('c', 'gender')->map(fn($v, $k) => "{$k}:{$v}")->implode(', ');

            $classCounts = DB::table('student_academic_histories as sah')
                ->join('course_classes as cc', 'cc.id', '=', 'sah.class_id')
                ->where('sah.school_id', $sid)->where('sah.academic_year_id', $yid)
                ->select('cc.name', DB::raw('count(*) as c'))
                ->groupBy('cc.name')->orderBy('cc.name')
                ->pluck('c', 'name')->map(fn($v, $k) => "{$k}:{$v}")->implode(', ');

            // Per-section breakdown: "Class 10-A:24, Class 10-B:22, Class 9-A:30 ..."
            $sectionCounts = DB::table('student_academic_histories as sah')
                ->join('course_classes as cc', 'cc.id', '=', 'sah.class_id')
                ->join('sections as sec', 'sec.id', '=', 'sah.section_id')
                ->where('sah.school_id', $sid)->where('sah.academic_year_id', $yid)
                ->select('cc.name as class_name', 'sec.name as section_name', DB::raw('count(*) as c'))
                ->groupBy('cc.name', 'sec.name')
                ->orderBy('cc.name')->orderBy('sec.name')
                ->get()
                ->map(fn($r) => "{$r->class_name}-{$r->section_name}:{$r->c}")
                ->implode(', ');

            // ── ATTENDANCE ────────────────────────────────────────────────
            $attToday = Attendance::where('school_id', $sid)->where('academic_year_id', $yid)->where('date', $today)
                ->select('status', DB::raw('count(*) as total'))->groupBy('status')
                ->pluck('total', 'status')->toArray();
            $present     = ($attToday['present'] ?? 0) + ($attToday['late'] ?? 0) + ($attToday['half_day'] ?? 0);
            $absent      = $attToday['absent'] ?? 0;
            $totalMarked = array_sum($attToday);
            $attPct      = $totalMarked > 0 ? round($present / $totalMarked * 100, 1) . '%' : 'Not marked yet';

            $weekStart = now()->startOfWeek()->toDateString();
            $weekAtt   = Attendance::where('school_id', $sid)->where('academic_year_id', $yid)
                ->where('date', '>=', $weekStart)->where('date', '<=', $today)
                ->selectRaw("SUM(CASE WHEN status IN ('present','late','half_day') THEN 1 ELSE 0 END) as p, COUNT(*) as t")
                ->first();
            $weekPct = ($weekAtt && $weekAtt->t > 0) ? round($weekAtt->p / $weekAtt->t * 100, 1) . '%' : 'N/A';

            $lowAtt = Attendance::where('attendances.school_id', $sid)->where('attendances.academic_year_id', $yid)
                ->where('attendances.date', '>=', now()->subDays(30)->toDateString())
                ->join('students', 'students.id', '=', 'attendances.student_id')
                ->select(DB::raw("(students.first_name||' '||students.last_name) as name"), DB::raw('COUNT(*) as t'),
                    DB::raw("SUM(CASE WHEN attendances.status IN ('present','late','half_day') THEN 1 ELSE 0 END) as p"))
                ->groupBy('attendances.student_id', 'students.first_name', 'students.last_name')
                ->havingRaw('COUNT(*) > 0')
                ->havingRaw("SUM(CASE WHEN attendances.status IN ('present','late','half_day') THEN 1 ELSE 0 END)*100.0/COUNT(*) < 75")
                ->orderByRaw("SUM(CASE WHEN attendances.status IN ('present','late','half_day') THEN 1 ELSE 0 END)*100.0/COUNT(*) ASC")
                ->limit(8)->get()
                ->map(fn($r) => "{$r->name}(" . round($r->p / $r->t * 100, 0) . "%)")->implode(', ');

            // ── FEES ──────────────────────────────────────────────────────
            $feeToday  = FeePayment::where('school_id', $sid)->where('academic_year_id', $yid)->whereDate('payment_date', $today)->where('amount_paid', '>', 0)->sum('amount_paid');
            $feeMonth  = FeePayment::where('school_id', $sid)->where('academic_year_id', $yid)->whereDate('payment_date', '>=', $monthStart)->where('amount_paid', '>', 0)->sum('amount_paid');
            $feeYear   = FeePayment::where('school_id', $sid)->where('academic_year_id', $yid)->where('amount_paid', '>', 0)->sum('amount_paid');
            // Structure-based pending: includes fee heads with no payment records
            $schoolPending = app(\App\Services\FeeService::class)->getSchoolPendingFees($sid, $yid);
            $pending   = $schoolPending['pending_fees'];
            $overdue   = count($schoolPending['pending_fee_students']);

            $topDue = collect($schoolPending['pending_fee_students'])
                ->map(fn($r) => "{$r['student']}(due:₹" . number_format($r['balance'], 0) . ")")
                ->implode('; ');

            // ── EXPENSES ──────────────────────────────────────────────────
            $expMonth = Expense::where('school_id', $sid)->where('academic_year_id', $yid)->where('expense_date', '>=', $monthStart)->sum('amount');
            $expYear  = Expense::where('school_id', $sid)->where('academic_year_id', $yid)->sum('amount');

            $expByCategory = Expense::where('expense_categories.school_id', $sid)
                ->where('expenses.academic_year_id', $yid)
                ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
                ->select('expense_categories.name', DB::raw('SUM(expenses.amount) as total'))
                ->groupBy('expense_categories.name')
                ->orderByRaw('SUM(expenses.amount) DESC')
                ->limit(5)->get()
                ->map(fn($r) => "{$r->name}:₹" . number_format($r->total, 0))->implode(', ');

            $expUnposted = Expense::where('school_id', $sid)->where('academic_year_id', $yid)->whereNull('gl_transaction_id')->count();

            // ── PAYROLL ────────────────────────────────────────────────────
            $curMonth = now()->month;
            $curYear  = now()->year;
            $payrollThisMonth = Payroll::where('school_id', $sid)->where('month', $curMonth)->where('year', $curYear);
            $payrollGenCount  = (clone $payrollThisMonth)->count();
            $payrollPaidCount = (clone $payrollThisMonth)->where('status', 'paid')->count();
            $payrollPendCount = $payrollGenCount - $payrollPaidCount;
            $payrollTotalNet  = (clone $payrollThisMonth)->where('status', 'paid')->sum('net_salary');
            $payrollUnposted  = (clone $payrollThisMonth)->where('status', 'paid')->whereNull('gl_transaction_id')->count();

            // ── TRANSFER CERTIFICATES ──────────────────────────────────────
            $tcRequested = TransferCertificate::where('school_id', $sid)->where('status', 'requested')->count();
            $tcApproved  = TransferCertificate::where('school_id', $sid)->where('status', 'approved')->count();
            $tcIssued    = TransferCertificate::where('school_id', $sid)->where('status', 'issued')->count();

            // ── GL / FINANCE HEALTH ────────────────────────────────────────
            $feeUnposted = FeePayment::where('school_id', $sid)->where('academic_year_id', $yid)
                ->where('status', 'paid')->whereNull('gl_transaction_id')->count();

            // ── STAFF ──────────────────────────────────────────────────────
            $staffStatus = DB::table('staff')->where('school_id', $sid)
                ->select('status', DB::raw('count(*) as c'))->groupBy('status')
                ->pluck('c', 'status')->map(fn($v, $k) => "{$k}:{$v}")->implode(', ');
            $staffLeaveToday = Leave::where('school_id', $sid)
                ->where('start_date', '<=', $today)->where('end_date', '>=', $today)->where('status', 'approved')->count();
            $staffLeavePending = Leave::where('school_id', $sid)->where('status', 'pending')->count();

            // ── STUDENT LEAVES ─────────────────────────────────────────────
            $stuLeavePending  = StudentLeave::where('school_id', $sid)->where('status', 'pending')->count();
            $stuLeaveApproved = StudentLeave::where('school_id', $sid)->where('start_date', '<=', $today)->where('end_date', '>=', $today)->where('status', 'approved')->count();

            // ── TRANSPORT ─────────────────────────────────────────────────
            $activeRoutes    = TransportRoute::where('school_id', $sid)->where('status', 'active')->count();
            $totalRoutes     = TransportRoute::where('school_id', $sid)->count();
            $activeVehicles  = TransportVehicle::where('school_id', $sid)->where('status', 'active')->count();
            $maintVehicles   = TransportVehicle::where('school_id', $sid)->where('status', 'maintenance')->count();
            $transportStudents = TransportStudentAllocation::where('school_id', $sid)->where('status', 'active')->count();
            $pickupTypes = TransportStudentAllocation::where('school_id', $sid)->where('status', 'active')
                ->select('pickup_type', DB::raw('count(*) as c'))->groupBy('pickup_type')
                ->pluck('c', 'pickup_type')->map(fn($v, $k) => "{$k}:{$v}")->implode(', ');

            // ── HOSTEL ────────────────────────────────────────────────────
            $hostelStudents  = HostelStudent::where('school_id', $sid)->where('status', 'Active')->count();
            $hostelRooms     = HostelRoom::where('school_id', $sid)->count();
            $hostelFull      = HostelRoom::where('school_id', $sid)->where('status', 'Full')->count();
            $hostelMaint     = HostelRoom::where('school_id', $sid)->where('status', 'Maintenance')->count();
            $totalBedCap     = HostelRoom::where('school_id', $sid)->sum('capacity');

            // ── ACADEMICS ─────────────────────────────────────────────────
            $totalClasses  = CourseClass::where('school_id', $sid)->count();
            $totalSections = Section::where('school_id', $sid)->forCurrentYear()->count();
            $totalSubjects = Subject::where('school_id', $sid)->count();

            $assignmentsDue = Assignment::where('school_id', $sid)->where('academic_year_id', $yid)
                ->where('due_date', '>=', now())->where('due_date', '<=', now()->addDays(7))->count();
            $assignmentsOverdue = Assignment::where('school_id', $sid)->where('academic_year_id', $yid)
                ->where('due_date', '<', now())->count();

            // ── EXAMS ─────────────────────────────────────────────────────
            $allExams = ExamSchedule::where('school_id', $sid)->where('academic_year_id', $yid)
                ->with(['examType', 'courseClass', 'scheduleSubjects.subject'])->get();
            $allExamLines = $monthExamLines = [];
            foreach ($allExams as $exam) {
                $allExamLines[] = "{$exam->examType?->name} | Class {$exam->courseClass?->name} | status:{$exam->status}";
                foreach ($exam->scheduleSubjects as $ss) {
                    if ($ss->exam_date && $ss->exam_date >= $monthStart && $ss->exam_date <= $monthEnd) {
                        $monthExamLines[] = "{$exam->examType?->name}/{$exam->courseClass?->name}/{$ss->subject?->name} on {$ss->exam_date}";
                    }
                }
            }

            // ── COMPLAINTS ────────────────────────────────────────────────
            $complaintStats = Complaint::where('school_id', $sid)
                ->select('status', DB::raw('count(*) as c'))->groupBy('status')
                ->pluck('c', 'status')->map(fn($v, $k) => "{$k}:{$v}")->implode(', ');
            $criticalComplaints = Complaint::where('school_id', $sid)->where('priority', 'Critical')->whereNotIn('status', ['Resolved', 'Closed'])->count();

            // ── VISITORS ──────────────────────────────────────────────────
            $visitorsToday = VisitorLog::where('school_id', $sid)->whereDate('in_time', $today)->count();
            $visitorsMonth = VisitorLog::where('school_id', $sid)->whereDate('in_time', '>=', $monthStart)->count();

            // ── GATE PASSES ───────────────────────────────────────────────
            $gatePassPending = GatePass::where('school_id', $sid)->where('status', 'Pending')->count();
            $gatePassToday   = GatePass::where('school_id', $sid)->whereDate('created_at', $today)->count();

            // ── HOLIDAYS ──────────────────────────────────────────────────
            $upcomingHolidays = Holiday::where('school_id', $sid)
                ->where('date', '>=', $today)->where('date', '<=', now()->addDays(30)->toDateString())
                ->orderBy('date')->limit(5)->get()
                ->map(fn($h) => "{$h->name}({$h->date})")->implode(', ');

            // ── BUILD OUTPUT ──────────────────────────────────────────────
            return "
=== LIVE SCHOOL DATA (Today: {$today}) ===
School: {$school->name} | Academic Year: {$year->name}

[STUDENTS]
Total active: {$totalStudents} | New this month: {$newThisMonth}
Gender: {$genderStats}
By class: {$classCounts}
By class-section: {$sectionCounts}

[ACADEMICS]
Classes: {$totalClasses} | Sections: {$totalSections} | Subjects: {$totalSubjects}
Assignments due in 7 days: {$assignmentsDue} | Overdue assignments: {$assignmentsOverdue}

[ATTENDANCE]
Today: Present={$present}, Absent={$absent}, Marked={$totalMarked}, Rate={$attPct}
This week avg: {$weekPct}
Low attendance students (<75% last 30d): " . ($lowAtt ?: 'None') . "

[FEES & FINANCE]
Fee collected today: ₹" . number_format($feeToday, 0) . " | This month: ₹" . number_format($feeMonth, 0) . " | This year: ₹" . number_format($feeYear, 0) . "
Pending balance: ₹" . number_format($pending, 0) . " from {$overdue} students
Students with pending fees (name, due amount, last payment date): " . ($topDue ?: 'None') . "
Expenses this month: ₹" . number_format($expMonth, 0) . " | This year: ₹" . number_format($expYear, 0) . "
Expense breakdown by category: " . ($expByCategory ?: 'No data') . "
GL unposted: {$feeUnposted} fee payments, {$expUnposted} expenses not yet posted to General Ledger

[PAYROLL — " . now()->format('F Y') . "]
Generated: {$payrollGenCount} | Paid: {$payrollPaidCount} | Pending payment: {$payrollPendCount}
Total salary disbursed this month: ₹" . number_format($payrollTotalNet, 0) . "
Payroll records not posted to GL: {$payrollUnposted}

[TRANSFER CERTIFICATES]
Pending approval: {$tcRequested} | Approved (not yet issued): {$tcApproved} | Issued this year: {$tcIssued}

[STAFF]
Status: {$staffStatus}
On approved leave today: {$staffLeaveToday} | Pending leave requests: {$staffLeavePending}

[STUDENT LEAVES]
Pending approvals: {$stuLeavePending} | Students on leave today: {$stuLeaveApproved}

[TRANSPORT]
Routes: {$activeRoutes} active / {$totalRoutes} total
Vehicles: {$activeVehicles} active, {$maintVehicles} in maintenance
Students allocated: {$transportStudents} ({$pickupTypes})

[HOSTEL]
Students: {$hostelStudents} active | Bed capacity: {$totalBedCap}
Rooms: {$hostelRooms} total, {$hostelFull} full, {$hostelMaint} in maintenance

[EXAMINATIONS]
All schedules: " . ($allExamLines ? implode('; ', $allExamLines) : 'None') . "
This month exams: " . ($monthExamLines ? implode('; ', $monthExamLines) : 'None scheduled') . "

[COMPLAINTS]
Status breakdown: " . ($complaintStats ?: 'None') . "
Critical unresolved: {$criticalComplaints}

[FRONT OFFICE]
Visitors today: {$visitorsToday} | This month: {$visitorsMonth}
Gate passes pending approval: {$gatePassPending} | Created today: {$gatePassToday}

[UPCOMING HOLIDAYS (next 30 days)]
" . ($upcomingHolidays ?: 'None') . "
=== END LIVE DATA ===";
        } catch (\Throwable $e) {
            return "=== LIVE DATA ERROR: " . $e->getMessage() . " ===";
        }
    }

    public function chat(Request $request)
    {
        $request->validate([
            'message'  => 'required|string|max:2000',
            'history'  => 'array',
            'page'     => 'nullable|string|max:200',
        ]);

        $school   = app('current_school');
        $user     = auth()->user();
        $userRole = optional($user->roles->first())->name ?? 'user';

        $liveData = $this->getLiveDataSummary();

        $systemContext = "You are an AI assistant built into a School ERP called \"{$school->name}\". Help staff, teachers, and admins use the system and understand their data.

COMPLETE ERP NAVIGATION MAP (A–Z):
ADMINISTRATION:
- Dashboard: /school/dashboard
- Academic Structure: /school/classes (classes), /school/sections, /school/subject-types, /school/subjects, /school/class-subjects (assign subjects)
- Academic Resources: /school/academic/diary, /school/academic/assignments, /school/academic/syllabus, /school/academic/resources, /school/academic/book-list
- Attendance: /school/attendance (mark), /school/attendance/report
- Examinations: /school/exam-terms, /school/exam-types, /school/grading-systems, /school/exam-assessments, /school/exam-schedules, /school/admit-cards, /school/exam-marks (marks entry), /school/exam-results, /school/report-cards, /school/question-papers (AI question paper)
- Schedule: /school/periods, /school/timetable
- Student Management: /school/registrations, /school/students (directory), /school/student-leaves, /school/student-leave-types, /school/roll-numbers, /school/transfer-certificates

FINANCE:
- Fee: /school/fee/collect, /school/fee/structure, /school/fee/groups, /school/fee/concessions, /school/fee/config (receipt settings)
- Finance Reports: /school/finance/due-report, /school/finance/day-book, /school/finance/fee-summary, /school/finance/reports
- Expenses: /school/expenses, /school/expense-categories
- Accounting: /school/finance/ledger-types, /school/finance/ledgers (chart of accounts), /school/finance/transactions
- Statements: /school/finance/statements/trial-balance, /school/finance/statements/profit-loss, /school/finance/statements/balance-sheet
- Finance Config: /school/finance/budgets, /school/finance/gl-config (GL auto-posting)

HR & STAFF:
- /school/departments, /school/designations, /school/staff (directory), /school/staff-attendance, /school/staff-attendance/report, /school/leaves (leave management), /school/leave-types, /school/payroll, /school/incharge (incharge assignment)

OPERATIONS:
- Front Office: /school/front-office, /school/front-office/visitors, /school/front-office/gate-passes, /school/front-office/gate-passes/scanner (QR), /school/front-office/complaints, /school/front-office/call-logs, /school/front-office/call-logs-follow-ups, /school/front-office/correspondence, /school/front-office/daily-report
- Hostel: /school/hostel, /school/hostel/hostels, /school/hostel/rooms, /school/hostel/allocations, /school/hostel/gate-passes, /school/hostel/visitors, /school/hostel/mess, /school/hostel/roll-call, /school/hostel/roll-call/report, /school/hostel/complaints, /school/hostel/mess/meal-report
- Transport: /school/transport, /school/transport/routes, /school/transport/vehicles, /school/transport/allocations, /school/transport/attendance (bus roll call), /school/transport/reports/route-report, /school/transport/reports/fee-defaulters, /school/transport/live, /school/transport/driver-tracking

COMMUNICATION:
- /school/communication/dashboard, /school/communication/announcements, /school/communication/emergency, /school/communication/logs, /school/communication/analytics, /school/communication/email-templates, /school/communication/scheduled, /school/communication/social-buzz
- Config: /school/communication/config/sms, /school/communication/config/whatsapp, /school/communication/config/voice
- Templates: /school/communication/templates/sms, /school/communication/templates/whatsapp, /school/communication/templates/voice, /school/communication/templates/push
- Other: /school/ai/insights (AI Intelligence Hub), /school/chat, /school/holidays

SETTINGS:
- /school/settings/general-config, /school/settings/asset-config, /school/settings/system-config, /school/settings/mobile-qr, /school/academic-years, /school/settings/rollover (setup wizard), /school/custom-fields, /school/settings/number-formats, /school/edit-requests, /school/roles-permissions, /school/users (user login management)
- Utility: /school/utility/activity-log, /school/utility/error-log

Current user: {$user->name} (Role: {$userRole})
Current page: {$request->page}

{$liveData}

RESPONSE RULES:
- For navigation: format as **Page Name** (`/exact/path`) — this renders as a clickable link
- For step-by-step: use numbered list with navigation paths at each step
- For data questions: use exact numbers from LIVE SCHOOL DATA above
- For amounts: use ₹ symbol
- Keep replies concise; use bullet points for lists
- Be friendly and professional

IMPORTANT — Respond ONLY with this exact JSON (no markdown, no code fences):
{\"reply\": \"your formatted response\", \"follow_ups\": [\"Short follow-up 1?\", \"Short follow-up 2?\", \"Short follow-up 3?\"]}

follow_ups: 3 short questions the user would naturally ask next.";

        // Build conversation history for Groq
        $messages = [
            ['role' => 'system', 'content' => $systemContext],
        ];

        foreach ($request->history ?? [] as $msg) {
            $messages[] = [
                'role'    => $msg['role'] === 'user' ? 'user' : 'assistant',
                'content' => $msg['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $request->message];

        try {
            $response = Http::withToken(config('services.groq.key'))
                ->timeout(45)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'       => 'llama-3.3-70b-versatile',
                    'messages'    => $messages,
                    'temperature' => 0.7,
                    'max_tokens'  => 800,
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Illuminate\Support\Facades\Log::error('AI chat connection timeout', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'The AI service took too long to respond. Please try again.'], 503);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AI chat unexpected error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'AI service error. Please try again.'], 503);
        }

        if ($response->failed()) {
            $errMsg = $response->json('error.message') ?? 'AI service unavailable. Please try again.';
            \Illuminate\Support\Facades\Log::error('Groq API error', ['status' => $response->status(), 'body' => $response->body()]);
            return response()->json(['error' => $errMsg], 503);
        }

        $raw    = $response->json('choices.0.message.content') ?? '';
        $start  = strpos($raw, '{');
        $end    = strrpos($raw, '}');
        $parsed = null;
        if ($start !== false && $end !== false) {
            $parsed = json_decode(substr($raw, $start, $end - $start + 1), true);
        }

        return response()->json([
            'reply'      => $parsed['reply']      ?? $raw,
            'follow_ups' => $parsed['follow_ups'] ?? [],
        ]);
    }

    public function generateReportComments(Request $request)
    {
        $request->validate([
            'students'   => 'required|array|min:1|max:60',
            'exam_name'  => 'required|string|max:100',
            'class_name' => 'required|string|max:100',
        ]);

        $school = app('current_school');

        // Build a compact student summary for the prompt
        $summaries = [];
        foreach ($request->students as $st) {
            $name       = trim(($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? ''));
            $percentage = $st['report_calculated']['overall_percentage'] ?? 0;
            $subjects   = collect($st['report_calculated']['subjects'] ?? [])->map(function ($sub) {
                $grade = $sub['grade'] ?? '';
                if ($sub['obtained'] === 'ABS') return "{$sub['subject_name']}: ABS";
                return "{$sub['subject_name']}: {$sub['obtained']}/{$sub['max']}" . ($grade ? " ({$grade})" : '');
            })->implode(', ');

            $summaries[] = "ID:{$st['id']} Name:{$name} Overall:{$percentage}% Subjects:[{$subjects}]";
        }

        $studentList = implode("\n", $summaries);
        $count = count($request->students);

        $prompt = "You are a professional school teacher writing report card remarks for {$school->name}.

Exam: {$request->exam_name}
Class: {$request->class_name}

Write a short, personalized, encouraging teacher's remark (1-2 sentences, max 25 words) for each student based on their marks and grades. Be specific to their performance. Use a professional tone.

Students:
{$studentList}

Respond ONLY with a valid JSON array in this exact format (no markdown, no extra text):
[{\"id\": 123, \"comment\": \"remark here\"}, ...]

Generate exactly {$count} comments, one per student ID.";

        $response = Http::withToken(config('services.groq.key'))
            ->timeout(60)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => 'llama-3.3-70b-versatile',
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.8,
                'max_tokens'  => 2000,
            ]);

        if ($response->failed()) {
            $errMsg = $response->json('error.message') ?? 'AI service unavailable.';
            return response()->json(['error' => $errMsg], 503);
        }

        $raw = $response->json('choices.0.message.content') ?? '';
        $start = strpos($raw, '[');
        $end   = strrpos($raw, ']');
        if ($start !== false && $end !== false) {
            $raw = substr($raw, $start, $end - $start + 1);
        }

        $comments = json_decode($raw, true);
        if (!is_array($comments)) {
            return response()->json(['error' => 'AI returned an unexpected format. Please try again.'], 500);
        }

        return response()->json(['comments' => $comments]);
    }
}
