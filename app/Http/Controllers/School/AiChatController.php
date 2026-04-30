<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\FeePayment;
use App\Services\Ai\AiToolRegistry;
use App\Services\GroqClient;
use App\Utils\AiJsonParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller
{
    private const MAX_TOOL_ITERATIONS = 3;

    public function __construct(
        private GroqClient $groq,
        private AiToolRegistry $tools
    ) {}

    /**
     * Lightweight live data summary — the bot can call tools for anything more specific.
     */
    private function quickSnapshot(): string
    {
        try {
            $school = app('current_school');
            $year   = app('current_academic_year');
            $sid    = $school->id;
            $yid    = $year?->id;
            $today  = now()->toDateString();

            $att = Attendance::where('school_id', $sid)
                ->when($yid, fn($q) => $q->where('academic_year_id', $yid))
                ->where('date', $today)
                ->selectRaw("
                    SUM(CASE WHEN status IN ('present','late','half_day') THEN 1 ELSE 0 END) as present,
                    COUNT(*) as marked
                ")->first();

            $attPct = ($att && $att->marked > 0) ? round($att->present / $att->marked * 100, 1) . '%' : 'Not marked yet';

            $feeToday = FeePayment::where('school_id', $sid)
                ->when($yid, fn($q) => $q->where('academic_year_id', $yid))
                ->whereDate('payment_date', $today)
                ->where('amount_paid', '>', 0)
                ->sum('amount_paid');

            return "School: {$school->name} | Year: " . ($year->name ?? '—') . " | Today: {$today}\n"
                 . "Today's attendance rate: {$attPct} | Fees collected today: ₹" . number_format($feeToday, 0);
        } catch (\Throwable $e) {
            return 'Live data summary unavailable.';
        }
    }

    public function chat(Request $request)
    {
        $request->validate([
            'message'      => 'required|string|max:2000',
            'history'      => 'array',
            'history.*.role' => 'string|in:user,assistant',
            'history.*.content' => 'string|max:4000',
            'page'         => 'nullable|string|max:200',
        ]);

        $messages = $this->buildMessages($request);
        $reply    = $this->runChatLoop($messages);

        if (isset($reply['error'])) {
            return response()->json(['error' => $reply['error']], $reply['status'] ?? 503);
        }

        $parsed = AiJsonParser::object($reply['content']);

        return response()->json([
            'reply'      => $parsed['reply']      ?? $reply['content'],
            'follow_ups' => $parsed['follow_ups'] ?? [],
        ]);
    }

    public function chatStream(Request $request)
    {
        $request->validate([
            'message'      => 'required|string|max:2000',
            'history'      => 'array',
            'history.*.role' => 'string|in:user,assistant',
            'history.*.content' => 'string|max:4000',
            'page'         => 'nullable|string|max:200',
        ]);

        $messages = $this->buildMessages($request, plainText: true);

        return response()->stream(function () use ($messages) {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            while (ob_get_level()) ob_end_flush();

            $this->groq->streamLive(
                $messages,
                'creative',
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

    private function buildMessages(Request $request, bool $plainText = false): array
    {
        $school   = app('current_school');
        $user     = auth()->user();
        $userRole = optional($user->roles->first())->name ?? 'user';
        $page     = $request->input('page');

        $snapshot = $this->quickSnapshot();
        $toolNote = $plainText
            ? ''
            : "When unsure, call a TOOL (e.g. `search_routes` for navigation, `count_attendance` for live numbers, `get_fee_defaulters` for outstanding fees, `search_student_by_name` to look up students). Tools return real database results.";

        $responseFormat = $plainText
            ? "Format navigation as **Page Name** (`/exact/path`). Use ₹ for currency. Use bullet points for lists. Be concise."
            : "Respond ONLY with this exact JSON (no markdown, no code fences):\n{\"reply\": \"your formatted response\", \"follow_ups\": [\"Q1?\", \"Q2?\", \"Q3?\"]}";

        $system = <<<SYS
You are an AI assistant built into the School ERP "{$school->name}". Help staff, teachers, and admins use the system and understand their data.

CURRENT CONTEXT:
- User: {$user->name} (Role: {$userRole})
- Page: {$page}

QUICK SCHOOL SNAPSHOT:
{$snapshot}

{$toolNote}

RESPONSE STYLE:
- For navigation answers, format as **Page Name** (`/route/path`) — these render as clickable links.
- For data questions, prefer calling a tool over guessing.
- Use ₹ for currency. Use bullet points for lists. Keep replies concise.
- Be friendly and professional.

{$responseFormat}
SYS;

        $messages = [['role' => 'system', 'content' => $system]];

        foreach ($request->input('history', []) as $msg) {
            $messages[] = [
                'role'    => $msg['role'] === 'user' ? 'user' : 'assistant',
                'content' => $msg['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $request->input('message')];

        return $messages;
    }

    /**
     * Multi-turn loop with tool dispatching. Caps at MAX_TOOL_ITERATIONS to avoid runaway loops.
     */
    private function runChatLoop(array $messages): array
    {
        $toolSpecs = $this->tools->specs();

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            $resp = $this->groq->chat($messages, 'creative', [
                'tools'       => $toolSpecs,
                'tool_choice' => 'auto',
            ]);

            if (!$resp['ok']) {
                return ['error' => $resp['error'], 'status' => $resp['status'] ?? 503];
            }

            $toolCalls = $resp['tool_calls'] ?? [];
            if (empty($toolCalls)) {
                return ['content' => $resp['content']];
            }

            // Append assistant message with tool calls for the next turn
            $messages[] = [
                'role'       => 'assistant',
                'content'    => $resp['content'] ?: null,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $call) {
                $name    = $call['function']['name'] ?? '';
                $argsRaw = $call['function']['arguments'] ?? '{}';
                $callId  = $call['id'] ?? null;

                $result = $this->tools->dispatch($callId ?? '', $name, is_string($argsRaw) ? $argsRaw : json_encode($argsRaw));

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $result['tool_call_id'],
                    'name'         => $result['name'],
                    'content'      => $result['content'],
                ];
            }
        }

        Log::warning('AI chat hit tool-call iteration cap', ['iterations' => self::MAX_TOOL_ITERATIONS]);
        return ['content' => '{"reply":"I needed too many lookups to answer that. Please ask a more specific question.","follow_ups":[]}'];
    }

    /**
     * Generate teacher remarks for a batch of students based on their report card data.
     */
    public function generateReportComments(Request $request)
    {
        $request->validate([
            'students'   => 'required|array|min:1|max:60',
            'exam_name'  => 'required|string|max:100',
            'class_name' => 'required|string|max:100',
        ]);

        $school = app('current_school');

        $summaries = [];
        foreach ($request->input('students') as $st) {
            $name       = trim(($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? ''));
            $percentage = $st['report_calculated']['overall_percentage'] ?? 0;
            $subjects   = collect($st['report_calculated']['subjects'] ?? [])->map(function ($sub) {
                $name     = $sub['subject_name'] ?? '';
                $grade    = $sub['grade'] ?? '';
                $gradeStr = $grade ? " ({$grade})" : '';

                // Cumulative / term / weighted reports: no flat obtained/max — use percentage
                if (!array_key_exists('obtained', $sub)) {
                    $pct = $sub['percentage'] ?? null;
                    return $pct !== null ? "{$name}: {$pct}%{$gradeStr}" : "{$name}{$gradeStr}";
                }

                // Single-exam raw report
                if ($sub['obtained'] === 'ABS') return "{$name}: ABS";
                return "{$name}: {$sub['obtained']}/{$sub['max']}{$gradeStr}";
            })->implode(', ');

            $summaries[] = "ID:{$st['id']} Name:{$name} Overall:{$percentage}% Subjects:[{$subjects}]";
        }

        $studentList = implode("\n", $summaries);
        $count       = count($request->input('students'));

        $prompt = "You are a professional school teacher writing report card remarks for {$school->name}.

Exam: {$request->input('exam_name')}
Class: {$request->input('class_name')}

Write a short, personalized, encouraging teacher's remark (1-2 sentences, max 25 words) for each student based on their marks and grades. Be specific to their performance. Use a professional tone.

Students:
{$studentList}

Respond ONLY with a valid JSON array in this exact format (no markdown, no extra text):
[{\"id\": 123, \"comment\": \"remark here\"}, ...]

Generate exactly {$count} comments, one per student ID.";

        $resp = $this->groq->complete($prompt, 'long', ['temperature' => 0.8]);
        if (!$resp['ok']) {
            return response()->json(['error' => $resp['error']], $resp['status'] ?? 503);
        }

        $comments = AiJsonParser::array($resp['content']);
        if (!is_array($comments)) {
            return response()->json(['error' => 'AI returned an unexpected format. Please try again.'], 500);
        }

        return response()->json(['comments' => $comments]);
    }
}
