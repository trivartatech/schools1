<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class OnlineQuizSeeder extends Seeder
{
    public function run(): void
    {
        $school   = DB::table('schools')->first();
        $schoolId = $school->id;
        $now      = Carbon::now();

        $adminUserId = DB::table('users')->where('school_id', $schoolId)
            ->whereIn('user_type', ['principal', 'admin', 'school_admin', 'teacher'])
            ->value('id');

        Schema::disableForeignKeyConstraints();
        DB::table('online_quiz_responses')->whereIn('attempt_id',
            DB::table('online_quiz_attempts')->where('school_id', $schoolId)->pluck('id')
        )->delete();
        DB::table('online_quiz_attempts')->where('school_id', $schoolId)->delete();
        DB::table('online_quiz_questions')->whereIn('quiz_id',
            DB::table('online_quizzes')->where('school_id', $schoolId)->pluck('id')
        )->delete();
        DB::table('online_quizzes')->where('school_id', $schoolId)->delete();
        Schema::enableForeignKeyConstraints();

        $subjects = DB::table('subjects')->where('school_id', $schoolId)->get()->keyBy('code');

        // ── 3 sample quizzes ──────────────────────────────────────────────────
        $quizzes = [
            [
                'subject_code' => 'MAT',
                'title'        => 'Mathematics — Quick MCQ Test',
                'description'  => 'A 10-minute warm-up quiz covering basic arithmetic and algebra.',
                'duration'     => 10,
                'questions'    => [
                    ['q' => 'What is 7 × 8?',                              'options' => ['54', '56', '58', '64'], 'correct' => 1],
                    ['q' => 'Solve: 3x + 2 = 14, x = ?',                   'options' => ['2', '3', '4', '5'],     'correct' => 2],
                    ['q' => 'What is the square root of 144?',             'options' => ['10', '11', '12', '14'], 'correct' => 2],
                    ['q' => 'What is 25% of 200?',                          'options' => ['25', '40', '50', '75'], 'correct' => 2],
                    ['q' => 'How many sides does a hexagon have?',          'options' => ['5', '6', '7', '8'],     'correct' => 1],
                ],
            ],
            [
                'subject_code' => 'SCI',
                'title'        => 'General Science Quiz',
                'description'  => 'Test your basics across physics, chemistry, and biology.',
                'duration'     => 15,
                'questions'    => [
                    ['q' => 'What planet is known as the Red Planet?',     'options' => ['Venus', 'Mars', 'Jupiter', 'Saturn'], 'correct' => 1],
                    ['q' => 'Chemical symbol for water?',                  'options' => ['H2O', 'O2', 'CO2', 'NaCl'],            'correct' => 0],
                    ['q' => 'Largest organ in the human body?',            'options' => ['Heart', 'Liver', 'Skin', 'Brain'],     'correct' => 2],
                    ['q' => 'What gas do plants absorb from the air?',     'options' => ['Oxygen', 'Carbon Dioxide', 'Nitrogen', 'Hydrogen'], 'correct' => 1],
                    ['q' => 'Speed of light is approximately?',             'options' => ['3 × 10^5 km/s', '3 × 10^8 m/s', '3 × 10^6 m/s', '3 × 10^7 km/s'], 'correct' => 1],
                ],
            ],
            [
                'subject_code' => 'ENG',
                'title'        => 'English — Grammar Quiz',
                'description'  => 'Identify correct grammar usage. 10 questions, 15 minutes.',
                'duration'     => 15,
                'questions'    => [
                    ['q' => 'Choose correct: "She ___ to school every day."',       'options' => ['go', 'goes', 'going', 'gone'], 'correct' => 1],
                    ['q' => 'Identify the noun: "The cat sat on the mat."',          'options' => ['sat', 'on', 'cat', 'the'],     'correct' => 2],
                    ['q' => 'Antonym of "ancient"?',                                  'options' => ['old', 'modern', 'classic', 'historic'], 'correct' => 1],
                    ['q' => 'Synonym of "rapid"?',                                    'options' => ['slow', 'fast', 'lazy', 'late'],         'correct' => 1],
                    ['q' => 'Plural of "child"?',                                     'options' => ['childs', 'childes', 'children', 'childrens'], 'correct' => 2],
                ],
            ],
        ];

        $studentIds = DB::table('students')->where('school_id', $schoolId)->limit(15)->pluck('id')->toArray();

        foreach ($quizzes as $q) {
            $totalMarks = count($q['questions']) * 1;
            $passMarks  = ceil($totalMarks * 0.4);

            $quizId = DB::table('online_quizzes')->insertGetId([
                'school_id'                => $schoolId,
                'subject_id'               => $subjects[$q['subject_code']]->id ?? null,
                'created_by'               => $adminUserId,
                'title'                    => $q['title'],
                'description'              => $q['description'],
                'type'                     => 'mcq',
                'duration_minutes'         => $q['duration'],
                'total_marks'              => $totalMarks,
                'pass_marks'               => $passMarks,
                'shuffle_questions'        => false,
                'shuffle_options'          => false,
                'show_result_immediately'  => true,
                'status'                   => 'published',
                'start_at'                 => $now->copy()->subDays(7),
                'end_at'                   => $now->copy()->addDays(7),
                'target_classes'           => null,
                'target_sections'          => null,
                'created_at'               => $now,
                'updated_at'               => $now,
            ]);

            // Insert questions
            $questionIds = [];
            foreach ($q['questions'] as $idx => $qq) {
                $opts = [];
                foreach ($qq['options'] as $oi => $optText) {
                    $opts[] = ['text' => $optText, 'is_correct' => $oi === $qq['correct']];
                }
                $questionIds[] = [
                    'id' => DB::table('online_quiz_questions')->insertGetId([
                        'quiz_id'        => $quizId,
                        'question_text'  => $qq['q'],
                        'type'           => 'mcq',
                        'marks'          => 1,
                        'options'        => json_encode($opts),
                        'correct_answer' => null,
                        'explanation'    => null,
                        'order'          => $idx,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]),
                    'correct' => $qq['correct'],
                ];
            }

            // ── Attempts: 5 students per quiz ───────────────────────────────
            if (empty($studentIds)) continue;
            $attemptStudents = array_slice($studentIds, 0, 5);
            foreach ($attemptStudents as $sid) {
                $startedAt = $now->copy()->subDays(rand(1, 5))->setTime(rand(9, 16), rand(0, 59));
                $duration  = rand(5, $q['duration']);
                $submittedAt = $startedAt->copy()->addMinutes($duration);

                $score = 0;
                $responseRows = [];
                foreach ($questionIds as $qData) {
                    $isCorrect = rand(0, 100) < 70; // 70% correct rate
                    $selected  = $isCorrect ? $qData['correct'] : (($qData['correct'] + rand(1, 3)) % 4);
                    $marks     = $isCorrect ? 1 : 0;
                    $score    += $marks;

                    $responseRows[] = [
                        'question_id'    => $qData['id'],
                        'answer'         => (string) $selected,
                        'is_correct'     => $isCorrect,
                        'marks_awarded'  => $marks,
                        'created_at'     => $submittedAt,
                        'updated_at'     => $submittedAt,
                    ];
                }

                $percentage = ($score / $totalMarks) * 100;
                $attemptId = DB::table('online_quiz_attempts')->insertGetId([
                    'quiz_id'      => $quizId,
                    'student_id'   => $sid,
                    'school_id'    => $schoolId,
                    'started_at'   => $startedAt,
                    'submitted_at' => $submittedAt,
                    'score'        => $score,
                    'percentage'   => round($percentage, 2),
                    'passed'       => $score >= $passMarks,
                    'status'       => 'graded',
                    'tab_switches' => rand(0, 3),
                    'created_at'   => $startedAt,
                    'updated_at'   => $submittedAt,
                ]);

                foreach ($responseRows as &$r) {
                    $r['attempt_id'] = $attemptId;
                }
                DB::table('online_quiz_responses')->insert($responseRows);
            }
        }

        $this->command->info('✅ Online quizzes seeded: 3 quizzes, 15 questions, 15 attempts.');
    }
}
