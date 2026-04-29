<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Services\GroqClient;
use App\Utils\AiJsonParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AiSuggestionsController extends Controller
{
    private const FALLBACKS = [
        'How many students are absent today?',
        'What is today\'s fee collection?',
        'Which students have low attendance?',
        'How many students have pending fees?',
        'What is this month\'s total fee collection?',
        'Who are the top fee defaulters?',
    ];

    public function __construct(private GroqClient $groq) {}

    public function index(Request $request)
    {
        $request->validate([
            'page'          => 'nullable|string|max:200',
            'context'       => 'nullable|string|max:60',
            'last_question' => 'nullable|string|max:300',
            'count'         => 'nullable|integer|min:3|max:10',
        ]);

        $page         = $request->input('page', '');
        $context      = $request->input('context', 'insights');
        $lastQuestion = $request->input('last_question', '');
        $count        = $request->integer('count') ?: 6;
        $school       = app('current_school');

        $cacheKey = 'ai_suggestions:' . $school->id . ':' . md5("$context|$page|$lastQuestion|$count");

        $cached = Cache::get($cacheKey);
        if ($cached) return response()->json(['suggestions' => $cached]);

        $hour       = now()->format('H');
        $contextHint = $context === 'chat'
            ? 'These are short navigation/help questions a school admin might ask the ERP assistant.'
            : 'These are short data questions about today\'s school operations.';

        $followupHint = $lastQuestion
            ? "The user just asked: \"$lastQuestion\". Suggest related but distinct follow-up questions."
            : 'Cover a mix of attendance, fees, students, and operations.';

        $prompt = <<<PROMPT
You generate suggested questions for the AI assistant of a school ERP. {$contextHint}

CURRENT TIME: hour {$hour} of 24
CURRENT PAGE: "{$page}"
{$followupHint}

Generate exactly {$count} short, specific, naturally-phrased questions (each under 70 chars). Avoid duplicates. Avoid yes/no questions.

Respond ONLY with a JSON array of strings:
["Question 1?", "Question 2?", ...]
PROMPT;

        $resp = $this->groq->complete($prompt, 'fast');

        if (!$resp['ok']) {
            return response()->json(['suggestions' => array_slice(self::FALLBACKS, 0, $count)]);
        }

        $list = AiJsonParser::array($resp['content']);
        if (!is_array($list) || count($list) < 3) {
            return response()->json(['suggestions' => array_slice(self::FALLBACKS, 0, $count)]);
        }

        $list = array_values(array_filter(array_slice($list, 0, $count), fn($q) => is_string($q) && strlen($q) <= 100));

        Cache::put($cacheKey, $list, now()->addMinutes(5));

        return response()->json(['suggestions' => $list]);
    }
}
