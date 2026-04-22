<?php

namespace App\Http\Controllers\School\Quiz;

use App\Http\Controllers\Controller;
use App\Models\CourseClass;
use App\Models\OnlineQuiz;
use App\Models\OnlineQuizAttempt;
use App\Models\OnlineQuizQuestion;
use App\Models\OnlineQuizResponse;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OnlineQuizController extends Controller
{
    // ── Teacher/Admin: Quiz list ──────────────────────────────────────
    public function index()
    {
        $schoolId = app('current_school_id');

        $quizzes = OnlineQuiz::where('school_id', $schoolId)
            ->withCount('questions', 'attempts')
            ->with('subject', 'createdBy')
            ->latest()
            ->get();

        return Inertia::render('School/Quiz/Index', ['quizzes' => $quizzes]);
    }

    // ── Create quiz page ──────────────────────────────────────────────
    public function create()
    {
        $schoolId = app('current_school_id');
        $subjects = Subject::where('school_id', $schoolId)->orderBy('name')->get(['id', 'name']);
        $classes  = CourseClass::where('school_id', $schoolId)->orderBy('sort_order')->with(['sections' => fn($q) => $q->forCurrentYear()->select('id','course_class_id','name')])->get(['id', 'name']);

        return Inertia::render('School/Quiz/Create', compact('subjects', 'classes'));
    }

    // ── Store quiz + questions ────────────────────────────────────────
    public function store(Request $request)
    {
        $schoolId  = app('current_school_id');
        $validated = $request->validate([
            'title'                    => 'required|string|max:255',
            'description'              => 'nullable|string',
            'subject_id'               => 'nullable|exists:subjects,id',
            'type'                     => 'required|in:mcq,descriptive,mixed',
            'duration_minutes'         => 'required|integer|min:1|max:480',
            'total_marks'              => 'required|numeric|min:1',
            'pass_marks'               => 'required|numeric|min:0',
            'shuffle_questions'        => 'boolean',
            'shuffle_options'          => 'boolean',
            'show_result_immediately'  => 'boolean',
            'status'                   => 'required|in:draft,published,closed',
            'start_at'                 => 'nullable|date',
            'end_at'                   => 'nullable|date|after_or_equal:start_at',
            'target_classes'           => 'nullable|array',
            'target_sections'          => 'nullable|array',
            'questions'                => 'required|array|min:1',
            'questions.*.question_text'=> 'required|string',
            'questions.*.type'         => 'required|in:mcq,true_false,short_answer,descriptive',
            'questions.*.marks'        => 'required|numeric|min:0',
            'questions.*.options'      => 'nullable|array',
            'questions.*.correct_answer' => 'nullable|string',
            'questions.*.explanation'  => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $schoolId) {
            $questions = $validated['questions'];
            unset($validated['questions']);

            $quiz = OnlineQuiz::create(array_merge($validated, [
                'school_id'  => $schoolId,
                'created_by' => auth()->id(),
            ]));

            foreach ($questions as $i => $q) {
                OnlineQuizQuestion::create([
                    'quiz_id'        => $quiz->id,
                    'question_text'  => $q['question_text'],
                    'type'           => $q['type'],
                    'marks'          => $q['marks'],
                    'options'        => $q['options'] ?? null,
                    'correct_answer' => $q['correct_answer'] ?? null,
                    'explanation'    => $q['explanation'] ?? null,
                    'order'          => $i + 1,
                ]);
            }
        });

        return redirect()->route('school.quiz.index')->with('success', 'Quiz created successfully.');
    }

    // ── Edit quiz ─────────────────────────────────────────────────────
    public function edit(OnlineQuiz $quiz)
    {
        abort_if($quiz->school_id !== app('current_school_id'), 403);
        $schoolId = app('current_school_id');
        $quiz->load('questions');
        $subjects = Subject::where('school_id', $schoolId)->orderBy('name')->get(['id', 'name']);
        $classes  = CourseClass::where('school_id', $schoolId)->orderBy('sort_order')->with(['sections' => fn($q) => $q->forCurrentYear()->select('id','course_class_id','name')])->get(['id', 'name']);

        return Inertia::render('School/Quiz/Create', compact('quiz', 'subjects', 'classes'));
    }

    // ── Update quiz ───────────────────────────────────────────────────
    public function update(Request $request, OnlineQuiz $quiz)
    {
        abort_if($quiz->school_id !== app('current_school_id'), 403);
        abort_if($quiz->attempts()->exists(), 422, 'Cannot edit a quiz that already has attempts.');

        $validated = $request->validate([
            'title'                   => 'required|string|max:255',
            'description'             => 'nullable|string',
            'subject_id'              => 'nullable|exists:subjects,id',
            'type'                    => 'required|in:mcq,descriptive,mixed',
            'duration_minutes'        => 'required|integer|min:1|max:480',
            'total_marks'             => 'required|numeric|min:1',
            'pass_marks'              => 'required|numeric|min:0',
            'shuffle_questions'       => 'boolean',
            'shuffle_options'         => 'boolean',
            'show_result_immediately' => 'boolean',
            'status'                  => 'required|in:draft,published,closed',
            'start_at'                => 'nullable|date',
            'end_at'                  => 'nullable|date|after_or_equal:start_at',
            'target_classes'          => 'nullable|array',
            'target_sections'         => 'nullable|array',
            'questions'               => 'required|array|min:1',
            'questions.*.question_text' => 'required|string',
            'questions.*.type'          => 'required|in:mcq,true_false,short_answer,descriptive',
            'questions.*.marks'         => 'required|numeric|min:0',
            'questions.*.options'       => 'nullable|array',
            'questions.*.correct_answer'=> 'nullable|string',
            'questions.*.explanation'   => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $quiz) {
            $questions = $validated['questions'];
            unset($validated['questions']);

            $quiz->update($validated);
            $quiz->questions()->delete();

            foreach ($questions as $i => $q) {
                OnlineQuizQuestion::create([
                    'quiz_id'        => $quiz->id,
                    'question_text'  => $q['question_text'],
                    'type'           => $q['type'],
                    'marks'          => $q['marks'],
                    'options'        => $q['options'] ?? null,
                    'correct_answer' => $q['correct_answer'] ?? null,
                    'explanation'    => $q['explanation'] ?? null,
                    'order'          => $i + 1,
                ]);
            }
        });

        return redirect()->route('school.quiz.index')->with('success', 'Quiz updated.');
    }

    public function destroy(OnlineQuiz $quiz)
    {
        abort_if($quiz->school_id !== app('current_school_id'), 403);
        $quiz->delete();
        return back()->with('success', 'Quiz deleted.');
    }

    // ── Results view (teacher) ────────────────────────────────────────
    public function results(OnlineQuiz $quiz)
    {
        abort_if($quiz->school_id !== app('current_school_id'), 403);
        $quiz->load(['questions', 'attempts.student', 'attempts.responses']);

        return Inertia::render('School/Quiz/Results', ['quiz' => $quiz]);
    }

    // ── Student: take quiz ────────────────────────────────────────────
    public function take(OnlineQuiz $quiz)
    {
        $user     = auth()->user();
        $student  = $user->student;
        abort_if(!$student, 403, 'Only students can take quizzes.');
        abort_if($quiz->school_id !== app('current_school_id'), 403);
        abort_if(!$quiz->is_active, 403, 'This quiz is not currently active.');

        // Check existing attempt
        $attempt = OnlineQuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->first();

        if ($attempt && in_array($attempt->status, ['submitted', 'auto_submitted', 'graded'])) {
            return redirect()->route('school.quiz.my-result', [$quiz->id, $attempt->id]);
        }

        if (!$attempt) {
            $attempt = OnlineQuizAttempt::create([
                'quiz_id'    => $quiz->id,
                'student_id' => $student->id,
                'school_id'  => app('current_school_id'),
                'started_at' => now(),
                'status'     => 'in_progress',
            ]);
        }

        $questions = $quiz->questions;
        if ($quiz->shuffle_questions) {
            $questions = $questions->shuffle();
        }

        return Inertia::render('School/Quiz/Take', [
            'quiz'     => $quiz->only('id', 'title', 'duration_minutes', 'type', 'shuffle_options', 'show_result_immediately', 'total_marks'),
            'questions'=> $questions->map(function ($q) use ($quiz) {
                $options = $q->options;
                if ($quiz->shuffle_options && $options) shuffle($options);
                return [
                    'id'            => $q->id,
                    'question_text' => $q->question_text,
                    'type'          => $q->type,
                    'marks'         => $q->marks,
                    'options'       => $options,
                ];
            }),
            'attempt'  => $attempt->only('id', 'started_at'),
            'timeLeft' => max(0, $quiz->duration_minutes * 60 - now()->diffInSeconds($attempt->started_at)),
        ]);
    }

    // ── Student: submit quiz ──────────────────────────────────────────
    public function submit(Request $request, OnlineQuiz $quiz)
    {
        $user    = auth()->user();
        $student = $user->student;
        abort_if(!$student, 403);

        $attempt = OnlineQuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $validated = $request->validate([
            'answers'             => 'required|array',
            'answers.*.question_id' => 'required|exists:online_quiz_questions,id',
            'answers.*.answer'      => 'nullable|string',
            'tab_switches'          => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $attempt, $quiz) {
            $totalScore  = 0;
            $autoGraded  = 0;

            foreach ($validated['answers'] as $ans) {
                $question  = OnlineQuizQuestion::find($ans['question_id']);
                $isCorrect = null;
                $awarded   = null;

                if (in_array($question->type, ['mcq', 'true_false'])) {
                    $isCorrect = $ans['answer'] !== null && $ans['answer'] === $question->correct_answer;
                    $awarded   = $isCorrect ? $question->marks : 0;
                    $totalScore += $awarded;
                    $autoGraded++;
                } elseif ($question->type === 'short_answer' && $question->correct_answer) {
                    $isCorrect = strtolower(trim($ans['answer'] ?? '')) === strtolower(trim($question->correct_answer));
                    $awarded   = $isCorrect ? $question->marks : 0;
                    $totalScore += $awarded;
                    $autoGraded++;
                }

                OnlineQuizResponse::updateOrCreate(
                    ['attempt_id' => $attempt->id, 'question_id' => $question->id],
                    ['answer' => $ans['answer'], 'is_correct' => $isCorrect, 'marks_awarded' => $awarded]
                );
            }

            $percentage = $quiz->total_marks > 0 ? round($totalScore / $quiz->total_marks * 100, 2) : 0;
            $allAutoGraded = $autoGraded === $quiz->questions()->count();

            $attempt->update([
                'submitted_at' => now(),
                'tab_switches' => $validated['tab_switches'] ?? $attempt->tab_switches,
                'score'        => $totalScore,
                'percentage'   => $percentage,
                'passed'       => $allAutoGraded ? ($totalScore >= $quiz->pass_marks) : null,
                'status'       => $allAutoGraded ? 'graded' : 'submitted',
            ]);
        });

        return response()->json([
            'score'      => $attempt->fresh()->score,
            'percentage' => $attempt->fresh()->percentage,
            'passed'     => $attempt->fresh()->passed,
            'attempt_id' => $attempt->id,
        ]);
    }

    // ── Student: my quizzes ───────────────────────────────────────────
    public function myQuizzes()
    {
        $user    = auth()->user();
        $student = $user->student;
        abort_if(!$student, 403);

        $schoolId = app('current_school_id');

        $quizzes = OnlineQuiz::where('school_id', $schoolId)
            ->where('status', 'published')
            ->with(['subject', 'attempts' => fn($q) => $q->where('student_id', $student->id)])
            ->get()
            ->map(function ($quiz) {
                $attempt = $quiz->attempts->first();
                return [
                    'id'               => $quiz->id,
                    'title'            => $quiz->title,
                    'subject'          => $quiz->subject?->name,
                    'duration_minutes' => $quiz->duration_minutes,
                    'total_marks'      => $quiz->total_marks,
                    'start_at'         => $quiz->start_at,
                    'end_at'           => $quiz->end_at,
                    'is_active'        => $quiz->is_active,
                    'attempt_status'   => $attempt?->status,
                    'score'            => $attempt?->score,
                    'percentage'       => $attempt?->percentage,
                    'passed'           => $attempt?->passed,
                    'attempt_id'       => $attempt?->id,
                ];
            });

        return Inertia::render('School/Quiz/MyQuizzes', ['quizzes' => $quizzes]);
    }

    // ── Student: view my result ───────────────────────────────────────
    public function myResult(OnlineQuiz $quiz, OnlineQuizAttempt $attempt)
    {
        $user = auth()->user();
        abort_if($attempt->student_id !== $user->student?->id, 403);
        abort_if(!$quiz->show_result_immediately && !in_array($attempt->status, ['graded']), 403, 'Results not yet available.');

        $attempt->load(['responses.question']);

        return Inertia::render('School/Quiz/MyResult', [
            'quiz'    => $quiz->only('id', 'title', 'total_marks', 'pass_marks', 'show_result_immediately'),
            'attempt' => $attempt,
        ]);
    }
}
