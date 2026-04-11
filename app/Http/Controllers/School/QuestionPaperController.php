<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Models\CourseClass;
use App\Models\QuestionPaper;
use App\Models\QuestionPaperItem;
use App\Models\QuestionPaperSection;
use App\Models\SyllabusTopic;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class QuestionPaperController extends Controller
{
    // ── List saved question papers ────────────────────────────────────────
    public function index(Request $request)
    {
        $papers = QuestionPaper::with(['courseClass', 'subject', 'createdBy'])
            ->where('school_id', app('current_school_id'))
            ->where('academic_year_id', app('current_academic_year_id'))
            ->when($request->query('class_id'), fn($q, $v) => $q->where('class_id', $v))
            ->when($request->query('subject_id'), fn($q, $v) => $q->where('subject_id', $v))
            ->latest()
            ->get();

        $classes = CourseClass::where('school_id', app('current_school_id'))
            ->orderBy('numeric_value')->get(['id', 'name']);

        return Inertia::render('School/QuestionPaper/Index', [
            'papers'  => $papers,
            'classes' => $classes,
            'filters' => $request->only(['class_id', 'subject_id']),
        ]);
    }

    // ── Configuration form ───────────────────────────────────────────────
    public function create()
    {
        $classes = CourseClass::where('school_id', app('current_school_id'))
            ->orderBy('numeric_value')->get(['id', 'name', 'numeric_value']);

        return Inertia::render('School/QuestionPaper/Create', [
            'classes' => $classes,
        ]);
    }

    // ── AJAX: subjects for a class ───────────────────────────────────────
    public function getSubjects(Request $request)
    {
        $classId = $request->query('class_id');

        $subjects = ClassSubject::with('subject:id,name,code')
            ->where('school_id', app('current_school_id'))
            ->where('course_class_id', $classId)
            ->where('is_co_scholastic', false)
            ->whereNull('section_id')
            ->get()
            ->pluck('subject')
            ->unique('id')
            ->values();

        return response()->json($subjects);
    }

    // ── AJAX: syllabus topics for class + subject ────────────────────────
    public function getTopics(Request $request)
    {
        $topics = SyllabusTopic::where('school_id', app('current_school_id'))
            ->where('class_id', $request->query('class_id'))
            ->where('subject_id', $request->query('subject_id'))
            ->orderBy('sort_order')
            ->get(['id', 'chapter_name', 'topic_name']);

        return response()->json($topics);
    }

    // ── AI: generate questions ───────────────────────────────────────────
    public function generate(Request $request)
    {
        $request->validate([
            'class_name'       => 'required|string|max:100',
            'class_level'      => 'required|integer',
            'subject_name'     => 'required|string|max:100',
            'exam_type'        => 'nullable|string|max:100',
            'total_marks'      => 'required|integer|min:10|max:200',
            'duration_minutes' => 'required|integer|min:15|max:300',
            'difficulty'       => 'required|in:easy,medium,hard,mixed',
            'topics'           => 'nullable|array',
            'sections'         => 'required|array|min:1|max:10',
            'sections.*.name'               => 'required|string|max:50',
            'sections.*.question_type'      => 'required|in:mcq,short_answer,long_answer,fill_blank,true_false',
            'sections.*.marks_per_question' => 'required|integer|min:1|max:20',
            'sections.*.num_questions'      => 'required|integer|min:1|max:30',
        ]);

        $topicList = !empty($request->topics)
            ? implode(', ', $request->topics)
            : 'All topics from the syllabus';

        $sectionSpec = '';
        foreach ($request->sections as $i => $sec) {
            $typeLabel = str_replace('_', ' ', ucfirst($sec['question_type']));
            $sectionSpec .= "- {$sec['name']}: {$sec['num_questions']} questions of type {$typeLabel}, {$sec['marks_per_question']} marks each\n";
        }

        $prompt = "You are an expert question paper designer for school examinations.

Subject: {$request->subject_name}
Class/Grade: {$request->class_name} (Grade {$request->class_level})
Exam Type: " . ($request->exam_type ?: 'General') . "
Total Marks: {$request->total_marks}
Duration: {$request->duration_minutes} minutes
Difficulty: {$request->difficulty}
Topics to cover: {$topicList}

Generate a question paper with these sections:
{$sectionSpec}
Rules:
- Questions must be appropriate for the specified grade level
- Cover the specified topics as evenly as possible
- For MCQ: provide exactly 4 options (A, B, C, D) and indicate the correct answer
- For true_false: the correct_answer should be \"True\" or \"False\"
- For fill_blank: use \"___\" in the question text to indicate the blank
- For difficulty \"mixed\": distribute as 30% easy, 50% medium, 20% hard
- Make questions clear, unambiguous, and exam-ready
- Include a correct answer / model answer for every question

Respond ONLY with valid JSON (no markdown, no code fences, no extra text):
{
  \"sections\": [
    {
      \"name\": \"Section A\",
      \"questions\": [
        {
          \"question_text\": \"...\",
          \"option_a\": \"...or null if not MCQ\",
          \"option_b\": \"...or null\",
          \"option_c\": \"...or null\",
          \"option_d\": \"...or null\",
          \"correct_answer\": \"...\",
          \"marks\": 1
        }
      ]
    }
  ]
}";

        try {
            $response = Http::timeout(90)->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . config('services.gemini.key'),
                [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.7,
                        'maxOutputTokens' => 8192,
                        'thinkingConfig'  => ['thinkingBudget' => 0],
                    ],
                ]
            );
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Question paper AI timeout', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'The AI service took too long to respond. Please try again.'], 503);
        } catch (\Throwable $e) {
            Log::error('Question paper AI error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'AI service error. Please try again.'], 503);
        }

        if ($response->failed()) {
            $errMsg = $response->json('error.message') ?? 'AI service unavailable. Please try again.';
            Log::error('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);
            return response()->json(['error' => $errMsg], 503);
        }

        $raw = $response->json('candidates.0.content.parts.0.text') ?? '';

        // Strip markdown code fences if present
        $raw = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $raw = preg_replace('/```\s*$/m', '', $raw);

        // Find JSON boundaries
        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');
        if ($start !== false && $end !== false) {
            $raw = substr($raw, $start, $end - $start + 1);
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['sections'])) {
            Log::error('Question paper AI bad format', ['raw' => $raw]);
            return response()->json(['error' => 'AI returned an unexpected format. Please try again.'], 500);
        }

        return response()->json($data);
    }

    // ── AI: regenerate a single section ──────────────────────────────────
    public function regenerateSection(Request $request)
    {
        $request->validate([
            'class_name'       => 'required|string|max:100',
            'class_level'      => 'required|integer',
            'subject_name'     => 'required|string|max:100',
            'difficulty'       => 'required|in:easy,medium,hard,mixed',
            'topics'           => 'nullable|array',
            'section'          => 'required|array',
            'section.name'               => 'required|string',
            'section.question_type'      => 'required|string',
            'section.marks_per_question' => 'required|integer',
            'section.num_questions'      => 'required|integer',
            'existing_questions' => 'nullable|array',
        ]);

        $sec = $request->section;
        $typeLabel = str_replace('_', ' ', ucfirst($sec['question_type']));
        $topicList = !empty($request->topics) ? implode(', ', $request->topics) : 'All topics';

        $avoidList = '';
        if (!empty($request->existing_questions)) {
            $avoidList = "\n\nAvoid repeating these questions from other sections:\n" .
                implode("\n", array_map(fn($q) => "- {$q}", array_slice($request->existing_questions, 0, 20)));
        }

        $prompt = "You are an expert question paper designer.

Subject: {$request->subject_name}
Class/Grade: {$request->class_name} (Grade {$request->class_level})
Difficulty: {$request->difficulty}
Topics: {$topicList}

Generate {$sec['num_questions']} questions of type {$typeLabel}, {$sec['marks_per_question']} marks each.
{$avoidList}

Rules:
- For MCQ: provide 4 options and the correct answer
- For true_false: correct_answer is \"True\" or \"False\"
- For fill_blank: use \"___\" in the question text
- Make questions clear, unambiguous, and exam-ready

Respond ONLY with valid JSON (no markdown, no code fences):
{
  \"questions\": [
    {
      \"question_text\": \"...\",
      \"option_a\": null,
      \"option_b\": null,
      \"option_c\": null,
      \"option_d\": null,
      \"correct_answer\": \"...\",
      \"marks\": {$sec['marks_per_question']}
    }
  ]
}";

        try {
            $response = Http::timeout(60)->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . config('services.gemini.key'),
                [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.7,
                        'maxOutputTokens' => 4096,
                        'thinkingConfig'  => ['thinkingBudget' => 0],
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Regenerate section AI error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'AI service error. Please try again.'], 503);
        }

        if ($response->failed()) {
            return response()->json(['error' => $response->json('error.message') ?? 'AI service unavailable.'], 503);
        }

        $raw = $response->json('candidates.0.content.parts.0.text') ?? '';
        $raw = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $raw = preg_replace('/```\s*$/m', '', $raw);
        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');
        if ($start !== false && $end !== false) {
            $raw = substr($raw, $start, $end - $start + 1);
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['questions'])) {
            return response()->json(['error' => 'AI returned an unexpected format. Please try again.'], 500);
        }

        return response()->json($data);
    }

    // ── Save paper to database ───────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'class_id'         => 'required|exists:course_classes,id',
            'subject_id'       => 'required|exists:subjects,id',
            'title'            => 'required|string|max:255',
            'exam_type'        => 'nullable|string|max:100',
            'total_marks'      => 'required|integer|min:1',
            'duration_minutes' => 'required|integer|min:1',
            'difficulty'       => 'required|in:easy,medium,hard,mixed',
            'instructions'     => 'nullable|string|max:2000',
            'sections'         => 'required|array|min:1',
            'sections.*.name'               => 'required|string',
            'sections.*.question_type'      => 'required|string',
            'sections.*.marks_per_question' => 'required|integer',
            'sections.*.num_questions'      => 'required|integer',
            'sections.*.instructions'       => 'nullable|string',
            'sections.*.questions'          => 'required|array|min:1',
            'sections.*.questions.*.question_text'   => 'required|string',
            'sections.*.questions.*.option_a'        => 'nullable|string',
            'sections.*.questions.*.option_b'        => 'nullable|string',
            'sections.*.questions.*.option_c'        => 'nullable|string',
            'sections.*.questions.*.option_d'        => 'nullable|string',
            'sections.*.questions.*.correct_answer'  => 'nullable|string',
            'sections.*.questions.*.marks'           => 'required|integer',
        ]);

        $paper = DB::transaction(function () use ($request) {
            $paper = QuestionPaper::create([
                'school_id'         => app('current_school_id'),
                'academic_year_id'  => app('current_academic_year_id'),
                'class_id'          => $request->class_id,
                'subject_id'        => $request->subject_id,
                'title'             => $request->title,
                'exam_type'         => $request->exam_type,
                'total_marks'       => $request->total_marks,
                'duration_minutes'  => $request->duration_minutes,
                'difficulty'        => $request->difficulty,
                'instructions'      => $request->instructions,
                'created_by'        => auth()->id(),
            ]);

            foreach ($request->sections as $si => $sec) {
                $section = $paper->sections()->create([
                    'name'               => $sec['name'],
                    'question_type'      => $sec['question_type'],
                    'marks_per_question' => $sec['marks_per_question'],
                    'num_questions'      => $sec['num_questions'],
                    'instructions'       => $sec['instructions'] ?? null,
                    'sort_order'         => $si,
                ]);

                foreach ($sec['questions'] as $qi => $q) {
                    $section->items()->create([
                        'question_text'  => $q['question_text'],
                        'option_a'       => $q['option_a'] ?? null,
                        'option_b'       => $q['option_b'] ?? null,
                        'option_c'       => $q['option_c'] ?? null,
                        'option_d'       => $q['option_d'] ?? null,
                        'correct_answer' => $q['correct_answer'] ?? null,
                        'marks'          => $q['marks'],
                        'sort_order'     => $qi,
                    ]);
                }
            }

            return $paper;
        });

        return redirect()->route('school.question-papers.show', $paper->id)
            ->with('success', 'Question paper saved successfully.');
    }

    // ── View saved paper ─────────────────────────────────────────────────
    public function show(QuestionPaper $questionPaper)
    {
        abort_if($questionPaper->school_id !== app('current_school_id'), 404);

        $questionPaper->load(['courseClass', 'subject', 'createdBy', 'sections.items']);

        return Inertia::render('School/QuestionPaper/Show', [
            'paper' => $questionPaper,
        ]);
    }

    // ── Download PDF ─────────────────────────────────────────────────────
    public function downloadPdf(QuestionPaper $questionPaper)
    {
        abort_if($questionPaper->school_id !== app('current_school_id'), 404);

        $questionPaper->load(['courseClass', 'subject', 'sections.items']);
        $school = app('current_school');

        $pdf = Pdf::loadView('pdf.question-paper', [
            'paper'  => $questionPaper,
            'school' => $school,
        ]);

        $pdf->setPaper('a4', 'portrait');

        $filename = str_replace(' ', '_', $questionPaper->title) . '.pdf';
        return $pdf->download($filename);
    }

    // ── Delete paper ─────────────────────────────────────────────────────
    public function destroy(QuestionPaper $questionPaper)
    {
        abort_if($questionPaper->school_id !== app('current_school_id'), 404);

        $questionPaper->delete();

        return redirect()->route('school.question-papers.index')
            ->with('success', 'Question paper deleted.');
    }
}
