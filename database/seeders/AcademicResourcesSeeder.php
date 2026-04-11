<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AcademicResourcesSeeder extends Seeder
{
    public function run(): void
    {
        $school         = DB::table('schools')->first();
        $schoolId       = $school->id;
        $now            = Carbon::now();
        $academicYearId = DB::table('academic_years')->where('school_id', $schoolId)->where('status', 'active')->value('id');

        // Clear existing data
        DB::statement('PRAGMA foreign_keys = OFF;');
        DB::table('assignment_submissions')->whereIn('assignment_id', DB::table('assignments')->where('school_id', $schoolId)->pluck('id'))->delete();
        DB::table('assignments')->where('school_id', $schoolId)->delete();
        DB::table('learning_materials')->where('school_id', $schoolId)->delete();
        DB::table('student_diaries')->where('school_id', $schoolId)->delete();
        DB::table('book_lists')->where('school_id', $schoolId)->delete();
        DB::table('online_classes')->where('school_id', $schoolId)->delete();
        DB::statement('PRAGMA foreign_keys = ON;');

        // Fetch staff (teachers) for teacher_id FK
        $staffIds = DB::table('staff')->where('school_id', $schoolId)->pluck('id')->toArray();
        if (empty($staffIds)) return;

        // Fetch classes and their sections + subjects
        $classes = DB::table('course_classes')->where('school_id', $schoolId)->get();
        $subjects = DB::table('subjects')->where('school_id', $schoolId)->get();

        $assignmentTitles = [
            'Chapter 1 Summary & Questions',
            'Map Work - Political India',
            'Science Experiment Report',
            'Grammar Practice — Tenses',
            'Mathematics Problem Set 3',
            'History Timeline Activity',
            'Reading Comprehension Passage',
            'Draw and Label the Digestive System',
            'Current Affairs Project',
            'Algebra Word Problems',
        ];

        $materialTitles = [
            'Chapter Notes — PDF',
            'NCERT Solutions',
            'Mind Map — Key Concepts',
            'Practice Question Bank',
            'Previous Year Paper 2025',
            'Video Lesson Reference',
            'Formula Sheet',
            'Revision Worksheet',
        ];

        $diaryEntries = [
            'Complete Exercise 3.1 from NCERT textbook. Read pages 45–52.',
            'Revise Chapter 4 — Motion and Time. Practice sums 1–10.',
            'Write a paragraph on "My Favourite Season". Min 100 words.',
            'Learn multiplication tables 12–15. Test tomorrow.',
            'Draw the water cycle diagram and label all processes.',
            'Read Chapter 6 and note down 5 new vocabulary words.',
            'Solve the 10 problems given in class on fractions.',
            'Complete the map activity — mark state capitals.',
            'Prepare for Friday spell-bee. Words list on pg 78.',
            'Finish the science project outline. Submit next Monday.',
        ];

        $assignmentCount  = 0;
        $submissionCount  = 0;
        $materialCount    = 0;
        $diaryCount       = 0;
        $bookCount        = 0;
        $onlineClassCount = 0;

        foreach ($classes as $cls) {
            $sections = DB::table('sections')->where('course_class_id', $cls->id)->get();
            if ($sections->isEmpty()) continue;

            $classSubjects = DB::table('class_subjects')
                ->where('school_id', $schoolId)
                ->where('class_id', $cls->id)
                ->pluck('subject_id')
                ->toArray();

            if (empty($classSubjects)) {
                $classSubjects = $subjects->pluck('id')->take(3)->toArray();
            }

            $section = $sections->first();
            $staffId = $staffIds[($cls->id - 1) % count($staffIds)];

            // Get students in this class
            $studentIds = DB::table('student_academic_histories')
                ->where('academic_year_id', $academicYearId)
                ->where('class_id', $cls->id)
                ->pluck('student_id')
                ->toArray();

            // ── Assignments (2 per class) ──────────────────────────────────────
            foreach (array_slice($classSubjects, 0, 2) as $subIdx => $subjectId) {
                $dueDate     = Carbon::now()->addDays(rand(3, 14));
                $assignmentId = DB::table('assignments')->insertGetId([
                    'school_id'       => $schoolId,
                    'academic_year_id'=> $academicYearId,
                    'class_id'        => $cls->id,
                    'section_id'      => $section->id,
                    'subject_id'      => $subjectId,
                    'teacher_id'      => $staffId,
                    'title'           => $assignmentTitles[($cls->id + $subIdx) % count($assignmentTitles)],
                    'description'     => 'Complete all parts carefully. Marks will be given for neatness and accuracy.',
                    'due_date'        => $dueDate,
                    'max_marks'       => 20,
                    'created_at'      => $now->copy()->subDays(5),
                    'updated_at'      => $now,
                ]);
                $assignmentCount++;

                // Submissions from ~70% of students
                foreach ($studentIds as $sIdx => $studentId) {
                    if ($sIdx % 10 < 7) { // 70%
                        $submittedAt = Carbon::now()->subDays(rand(0, 3));
                        DB::table('assignment_submissions')->insert([
                            'assignment_id' => $assignmentId,
                            'student_id'    => $studentId,
                            'submitted_at'  => $submittedAt,
                            'content'       => 'Completed all questions as instructed.',
                            'marks'         => rand(12, 20),
                            'remarks'       => 'Good effort.',
                            'is_late'       => $submittedAt->gt($dueDate),
                            'created_at'    => $submittedAt,
                            'updated_at'    => $submittedAt,
                        ]);
                        $submissionCount++;
                    }
                }
            }

            // ── Learning Materials (3 per class) ──────────────────────────────
            foreach (array_slice($classSubjects, 0, 3) as $subIdx => $subjectId) {
                DB::table('learning_materials')->insert([
                    'school_id'   => $schoolId,
                    'class_id'    => $cls->id,
                    'section_id'  => $section->id,
                    'subject_id'  => $subjectId,
                    'teacher_id'  => $staffId,
                    'title'       => $materialTitles[($cls->id + $subIdx) % count($materialTitles)],
                    'type'        => ['pdf', 'document', 'image', 'video_link'][$subIdx % 4],
                    'file_path'   => 'materials/' . $schoolId . '/class_' . $cls->id . '_sub_' . $subjectId . '_' . ($subIdx + 1) . '.pdf',
                    'chapter_name'=> 'Chapter ' . ($cls->id),
                    'description' => 'Study material shared for reference and exam preparation.',
                    'is_published'=> true,
                    'created_at'  => $now->copy()->subDays(rand(1, 20)),
                    'updated_at'  => $now,
                ]);
                $materialCount++;
            }

            // ── Student Diaries (last 5 school days) ──────────────────────────
            for ($d = 0; $d < 5; $d++) {
                $diaryDate = Carbon::now()->subDays($d + 1);
                // Skip weekends
                if ($diaryDate->isWeekend()) continue;

                $subjectId = $classSubjects[0];
                DB::table('student_diaries')->insert([
                    'school_id'       => $schoolId,
                    'academic_year_id'=> $academicYearId,
                    'class_id'        => $cls->id,
                    'section_id'      => $section->id,
                    'subject_id'      => $subjectId,
                    'teacher_id'      => $staffId,
                    'date'            => $diaryDate->format('Y-m-d'),
                    'content'         => $diaryEntries[($cls->id + $d) % count($diaryEntries)],
                    'created_at'      => $diaryDate, 'updated_at' => $diaryDate,
                ]);
                $diaryCount++;
            }

            // ── Book List ──────────────────────────────────────────────────────
            $books = [
                ['book_name' => 'NCERT Textbook',           'publisher' => 'NCERT', 'author' => 'Various'],
                ['book_name' => 'Golden Guide',             'publisher' => 'Golden Books', 'author' => 'Dr. R.K. Gupta'],
                ['book_name' => 'RD Sharma Mathematics',   'publisher' => 'Dhanpat Rai', 'author' => 'R.D. Sharma'],
                ['book_name' => 'Wren & Martin English',   'publisher' => 'S. Chand', 'author' => 'P.C. Wren'],
                ['book_name' => 'Lakhmir Singh Science',   'publisher' => 'S. Chand', 'author' => 'Lakhmir Singh'],
            ];

            foreach (array_slice($classSubjects, 0, count($books)) as $subIdx => $subjectId) {
                DB::table('book_lists')->insert([
                    'school_id'       => $schoolId,
                    'academic_year_id'=> $academicYearId,
                    'class_id'        => $cls->id,
                    'subject_id'      => $subjectId,
                    'book_name'       => $books[$subIdx % count($books)]['book_name'],
                    'publisher'       => $books[$subIdx % count($books)]['publisher'],
                    'author'          => $books[$subIdx % count($books)]['author'],
                    'isbn'            => '978-' . rand(100000000, 999999999),
                    'created_at'      => $now, 'updated_at' => $now,
                ]);
                $bookCount++;
            }

            // ── Online Classes (2 upcoming + 1 past per class) ────────────────
            $platforms   = ['Google Meet', 'Zoom', 'Microsoft Teams'];
            $meetLinks   = ['https://meet.google.com/abc-defg-hij', 'https://zoom.us/j/123456789', 'https://teams.microsoft.com/l/meetup/xyz'];

            foreach ([1, -2] as $dayOffset) {
                $startTime = Carbon::now()->addDays($dayOffset)->setHour(10)->setMinute(0)->setSecond(0);
                DB::table('online_classes')->insert([
                    'school_id'   => $schoolId,
                    'class_id'    => $cls->id,
                    'section_id'  => $section->id,
                    'subject_id'  => $classSubjects[0],
                    'teacher_id'  => $staffId,
                    'start_time'  => $startTime,
                    'end_time'    => $startTime->copy()->addHour(),
                    'meeting_link'=> $meetLinks[$cls->id % count($meetLinks)],
                    'platform'    => $platforms[$cls->id % count($platforms)],
                    'created_at'  => $now, 'updated_at' => $now,
                ]);
                $onlineClassCount++;
            }
        }

        $this->command->info('✅ Academic Resources seeded!');
        $this->command->info("   - {$assignmentCount} Assignments, {$submissionCount} Submissions");
        $this->command->info("   - {$materialCount} Learning Materials");
        $this->command->info("   - {$diaryCount} Diary Entries");
        $this->command->info("   - {$bookCount} Book List entries");
        $this->command->info("   - {$onlineClassCount} Online Classes");
    }
}
