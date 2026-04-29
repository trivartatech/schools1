<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\School;
use App\Models\AcademicYear;
use App\Models\Subject;
use App\Models\CourseClass;
use App\Models\Section;
use App\Models\ExamTerm;
use App\Models\ExamType;
use App\Models\ExamAssessment;
use App\Models\ExamAssessmentItem;
use App\Models\GradingSystem;
use App\Models\ExamSchedule;
use App\Models\ExamScheduleSubject;

class ExaminationModuleSeeder extends Seeder
{
    public function run()
    {
        try {
            $school = School::first();
            $academicYear = AcademicYear::where('school_id', $school->id)->where('is_current', true)->first()
                          ?? AcademicYear::where('school_id', $school->id)->where('status', 'active')->first()
                          ?? AcademicYear::where('school_id', $school->id)->latest()->first();

            $schoolId = $school->id;
            $academicYearId = $academicYear->id;

            $this->command->info("Seeding for School: {$school->name} (ID: {$schoolId}), AY: {$academicYear->name} (ID: {$academicYearId})");

            $this->command->info('1. Clearing data...');
            \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
            DB::table('exam_marks')->delete();
            DB::table('exam_schedule_subject_marks')->delete();
            DB::table('exam_schedule_sections')->delete();
            DB::table('exam_schedule_subjects')->delete();
            DB::table('exam_schedules')->delete();
            DB::table('exam_assessment_items')->delete();
            DB::table('exam_assessments')->delete();
            DB::table('exam_types')->delete();
            DB::table('exam_terms')->delete();
            \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

            // Setup — CCE pattern: 2 terms, 4 Formative + 2 Summative assessments
            $term1 = ExamTerm::create(['school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'name' => 'T1', 'display_name' => 'Term 1']);
            $term2 = ExamTerm::create(['school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'name' => 'T2', 'display_name' => 'Term 2']);

            // CCE: Term 1 → FA1, FA2, SA1 ; Term 2 → FA3, FA4, SA2
            // Weightage: each FA = 10, each SA = 30 (total = 100 per academic year)
            $examTypesSpec = [
                ['code' => 'FA1', 'display' => 'Formative Assessment 1', 'term' => $term1, 'weightage' => 10],
                ['code' => 'FA2', 'display' => 'Formative Assessment 2', 'term' => $term1, 'weightage' => 10],
                ['code' => 'SA1', 'display' => 'Summative Assessment 1', 'term' => $term1, 'weightage' => 30],
                ['code' => 'FA3', 'display' => 'Formative Assessment 3', 'term' => $term2, 'weightage' => 10],
                ['code' => 'FA4', 'display' => 'Formative Assessment 4', 'term' => $term2, 'weightage' => 10],
                ['code' => 'SA2', 'display' => 'Summative Assessment 2', 'term' => $term2, 'weightage' => 30],
            ];

            $examTypesMap = [];
            $weightageMap = [];
            foreach ($examTypesSpec as $spec) {
                $t = ExamType::create([
                    'school_id'        => $schoolId,
                    'academic_year_id' => $academicYearId,
                    'exam_term_id'     => $spec['term']->id,
                    'name'             => $spec['code'],
                    'code'             => $spec['code'],
                    'display_name'     => $spec['display'],
                    'classification'   => 'main',
                ]);
                $examTypesMap[$spec['code']] = $t->id;
                $weightageMap[$spec['code']] = $spec['weightage'];
            }

            $mainAssessment = ExamAssessment::create(['school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'name' => 'Scholastic']);
            $thItem = ExamAssessmentItem::create(['exam_assessment_id' => $mainAssessment->id, 'school_id' => $schoolId, 'name' => 'Theory', 'code' => 'TH', 'sort_order' => 1]);
            $iaItem = ExamAssessmentItem::create(['exam_assessment_id' => $mainAssessment->id, 'school_id' => $schoolId, 'name' => 'IA', 'code' => 'IA', 'sort_order' => 2]);
            
            $coAssessment = ExamAssessment::create(['school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'name' => 'Co-Scholastic']);
            $coItem = ExamAssessmentItem::create(['exam_assessment_id' => $coAssessment->id, 'school_id' => $schoolId, 'name' => 'Grade', 'code' => 'GR', 'sort_order' => 1]);

            $scholasticGrading = GradingSystem::where('type', 'scholastic')->where('school_id', $schoolId)->first();
            $coScholasticGrading = GradingSystem::where('type', 'co_scholastic')->where('school_id', $schoolId)->first();

            $classes = CourseClass::where('school_id', $schoolId)->get();
            $allSubjects = Subject::where('school_id', $schoolId)->get();
            $scholasticSubjects = $allSubjects->filter(fn($s) => !($s->is_co_scholastic || in_array($s->name, ['Drawing', 'Music', 'PE', 'Physical Education'])));

            $this->command->info('2. Creating schedules...');
            foreach ($classes as $cls) {
                foreach ($examTypesMap as $code => $typeId) {
                    $schedule = ExamSchedule::create([
                        'school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'exam_type_id' => $typeId,
                        'course_class_id' => $cls->id, 'weightage' => $weightageMap[$code] ?? 25,
                        'has_co_scholastic' => true, 'scholastic_grading_system_id' => $scholasticGrading->id,
                        'co_scholastic_grading_system_id' => $coScholasticGrading?->id, 'status' => 'published',
                    ]);

                    $sections = Section::where('course_class_id', $cls->id)->pluck('id')->toArray();
                    $schedule->sections()->sync($sections);

                    foreach ($scholasticSubjects->random(min(5, $scholasticSubjects->count())) as $sub) {
                        $ess = ExamScheduleSubject::create(['exam_schedule_id' => $schedule->id, 'subject_id' => $sub->id, 'exam_assessment_id' => $mainAssessment->id, 'is_co_scholastic' => false, 'grading_system_id' => $scholasticGrading?->id, 'exam_date' => now(), 'exam_time' => '10:00:00']);
                        DB::table('exam_schedule_subject_marks')->insert([
                            ['exam_schedule_subject_id' => $ess->id, 'exam_assessment_item_id' => $thItem->id, 'max_marks' => 80, 'passing_marks' => 26],
                            ['exam_schedule_subject_id' => $ess->id, 'exam_assessment_item_id' => $iaItem->id, 'max_marks' => 20, 'passing_marks' => 7],
                        ]);
                    }

                    foreach (['Drawing', 'Music', 'PE'] as $coName) {
                        $cosub = Subject::updateOrCreate(['school_id' => $schoolId, 'name' => $coName], ['is_co_scholastic' => true]);
                        $cess = ExamScheduleSubject::create(['exam_schedule_id' => $schedule->id, 'subject_id' => $cosub->id, 'exam_assessment_id' => $coAssessment->id, 'is_co_scholastic' => true, 'grading_system_id' => $coScholasticGrading?->id]);
                        DB::table('exam_schedule_subject_marks')->insert(['exam_schedule_subject_id' => $cess->id, 'exam_assessment_item_id' => $coItem->id, 'max_marks' => 100, 'passing_marks' => 33]);
                    }
                }
            }

            $this->command->info('3. Seeding marks...');
            $allSchedules = ExamSchedule::with(['scheduleSubjects.markConfigs', 'sections'])->get();
            $marksInserted = 0;
            foreach ($allSchedules as $schedule) {
                $sectionIds = $schedule->sections->pluck('id')->toArray();
                $studentIds = DB::table('student_academic_histories')
                    ->whereIn('section_id', $sectionIds)
                    ->where('academic_year_id', $academicYearId)
                    ->pluck('student_id')
                    ->toArray();

                foreach ($studentIds as $studentId) {
                    foreach ($schedule->scheduleSubjects as $ss) {
                        foreach ($ss->markConfigs as $mConfig) {
                            DB::table('exam_marks')->insert([
                                'school_id'                => $schoolId,
                                'academic_year_id'         => $academicYearId,
                                'student_id'               => $studentId,
                                'exam_schedule_subject_id' => $ss->id,
                                'exam_assessment_item_id'  => $mConfig->exam_assessment_item_id,
                                'marks_obtained'           => rand(30, 95),
                                'is_absent'                => false,
                                'created_at'               => now(), 'updated_at' => now(),
                            ]);
                            $marksInserted++;
                        }
                    }
                }
            }
            $this->command->info("Final Success! Inserted {$marksInserted} marks.");
        } catch (\Exception $e) {
            $this->command->error('GLOBAL FAIL: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}
