<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\School;
use App\Models\AcademicYear;
use App\Models\CourseClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\User;
use App\Models\ExamType;
use App\Models\ExamTerm;
use App\Models\ExamSchedule;
use App\Models\ExamAssessment;
use App\Models\ExamScheduleSubject;
use App\Models\ExamMark;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class CBSEDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $school = School::first() ?? School::create(['name' => 'DPS North Campus', 'contact_email' => 'info@dps.edu']);
        $academicYear = AcademicYear::where('school_id', $school->id)->where('is_current', true)->first()
                     ?? AcademicYear::where('school_id', $school->id)->where('status', 'active')->first()
                     ?? AcademicYear::where('school_id', $school->id)->latest()->first();

        // 1. Core CBSE Subjects
        $cbseSubjects = [
            ['name' => 'English Language & Literature', 'code' => '184', 'type' => 'scholastic'],
            ['name' => 'Hindi Course-B', 'code' => '085', 'type' => 'scholastic'],
            ['name' => 'Mathematics Standard', 'code' => '041', 'type' => 'scholastic'],
            ['name' => 'Science', 'code' => '086', 'type' => 'scholastic'],
            ['name' => 'Social Science', 'code' => '087', 'type' => 'scholastic'],
            ['name' => 'Information Technology', 'code' => '402', 'type' => 'scholastic']
        ];

        $subjectMap = [];
        foreach ($cbseSubjects as $subData) {
            $subject = Subject::firstOrCreate(
                ['school_id' => $school->id, 'code' => $subData['code']],
                ['name' => $subData['name'], 'type' => $subData['type']]
            );
            $subjectMap[$subData['code']] = $subject;
        }

        // 2. Classes & Sections (Class 1 to 10)
        $classesToCreate = ['Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10'];
        $sectionsToCreate = ['A', 'B'];

        $courseClasses = [];
        $allSections = [];

        foreach ($classesToCreate as $className) {
            $class = CourseClass::firstOrCreate(
                ['school_id' => $school->id, 'name' => $className],
                ['is_active' => true]
            );
            $courseClasses[] = $class;

            foreach ($sectionsToCreate as $secName) {
                $section = Section::firstOrCreate(
                    ['school_id' => $school->id, 'course_class_id' => $class->id, 'name' => $secName],
                    ['capacity' => 40]
                );
                $allSections[] = $section;
            }
        }

        // 3. Exam Terms & Types (FA1, FA2, SA1)
        $examTerm = ExamTerm::firstOrCreate(
            ['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'name' => 'Term 1'],
            ['display_name' => 'Term 1 Assessment']
        );

        $examTypes = ['Formative Assessment 1', 'Formative Assessment 2', 'Summative Assessment 1'];
        $dbExamTypes = [];
        foreach ($examTypes as $etName) {
            $dbExamTypes[] = ExamType::firstOrCreate(
                ['school_id' => $school->id, 'name' => $etName, 'academic_year_id' => $academicYear->id, 'exam_term_id' => $examTerm->id]
            );
        }

        $practicalAssessment = ExamAssessment::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'CBSE Theory + Internal'],
            ['academic_year_id' => $academicYear->id, 'description' => 'Standard 80/20 split']
        );

        if ($practicalAssessment->items()->count() === 0) {
            $practicalAssessment->items()->createMany([
                ['school_id' => $school->id, 'name' => 'Theory', 'code' => 'TH', 'sort_order' => 1],
                ['school_id' => $school->id, 'name' => 'Internal Assessment', 'code' => 'IA', 'sort_order' => 2]
            ]);
        }
        $assessmentItems = $practicalAssessment->items;

        // 5. Use existing students from SchoolDummyDataSeeder (no duplicate creation)
        $students = Student::whereHas('academicHistories', function ($q) use ($academicYear) {
            $q->where('academic_year_id', $academicYear->id);
        })->where('school_id', $school->id)->get();

        // 6. Create SA1 Exam Schedules & Marks
        $sa1Type = $dbExamTypes[2]; // Summative Assessment 1

        foreach ($courseClasses as $class) {
            $schedule = ExamSchedule::firstOrCreate([
                'school_id' => $school->id,
                'academic_year_id' => $academicYear->id,
                'exam_type_id' => $sa1Type->id,
                'course_class_id' => $class->id,
            ], [
                'status' => 'published',
                'has_co_scholastic' => false,
            ]);

            // Sync Sections
            $classSections = Section::where('course_class_id', $class->id)->pluck('id')->toArray();
            $schedule->sections()->syncWithoutDetaching($classSections);

            // Add Subjects to Schedule
            foreach ($subjectMap as $code => $subject) {
                $scheduleSubject = ExamScheduleSubject::firstOrCreate([
                    'exam_schedule_id' => $schedule->id,
                    'subject_id' => $subject->id,
                ], [
                    'exam_assessment_id' => $practicalAssessment->id,
                    'is_enabled' => true,
                    'exam_date' => Carbon::now()->addDays(rand(1, 10))->format('Y-m-d'),
                    'duration_minutes' => 180
                ]);

                // Create Max Mark overrides for this Schedule
                $itemMaxMarks = [];
                foreach ($assessmentItems as $item) {
                    $max = ($item->name === 'Theory') ? 80 : 20;
                    $pass = ($item->name === 'Theory') ? 26 : 6;
                    
                    $scheduleSubject->markConfigs()->firstOrCreate(
                        ['exam_assessment_item_id' => $item->id],
                        [
                            'max_marks' => $max,
                            'passing_marks' => $pass
                        ]
                    );
                    $itemMaxMarks[$item->id] = $max;
                }

                // 7. Inject realistic marks for the students
                $classSections = $schedule->sections()->pluck('sections.id')->toArray();
                $classStudents = Student::whereHas('academicHistories', function($q) use ($classSections, $academicYear) {
                    $q->whereIn('section_id', $classSections)
                      ->where('academic_year_id', $academicYear->id);
                })->get();

                foreach ($classStudents as $student) {
                    // Bias Math and Science slightly to create varied averages
                    $baseCurve = rand(30, 75); // Range for Theory
                    $internalCurve = rand(10, 19); // Range for Internal
                    
                    if ($code === '041') $baseCurve = rand(20, 75); // Math spread out
                    if ($code === '184') $baseCurve = rand(50, 75); // English generally higher
                    
                    // 5% chance of being absent
                    $isAbsent = (rand(1, 100) <= 5);

                    foreach ($assessmentItems as $item) {
                        $obtained = null;
                        if (!$isAbsent) {
                            $obtained = ($item->name === 'Theory') ? $baseCurve + rand(0, 5) : $internalCurve;
                            $maxAllowed = $itemMaxMarks[$item->id];
                            if ($obtained > $maxAllowed) $obtained = $maxAllowed;
                        }

                        ExamMark::firstOrCreate(
                            [
                                'student_id' => $student->id,
                                'exam_schedule_subject_id' => $scheduleSubject->id,
                                'exam_assessment_item_id' => $item->id,
                            ],
                            [
                                'school_id' => $school->id,
                                'academic_year_id' => $academicYear->id,
                                'marks_obtained' => $obtained,
                                'is_absent' => $isAbsent,
                            ]
                        );
                    }
                }
            }
        }
    }
}
