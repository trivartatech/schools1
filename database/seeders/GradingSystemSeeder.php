<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\School;
use App\Models\AcademicYear;
use App\Models\GradingSystem;

class GradingSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $school = School::first();
        $academicYear = AcademicYear::where('school_id', $school->id)->where('is_current', true)->first()
                     ?? AcademicYear::where('school_id', $school->id)->where('status', 'active')->first()
                     ?? AcademicYear::where('school_id', $school->id)->latest()->first();

        if (!$school || !$academicYear) {
            $this->command->error('No active school or academic year found. Please run School, AcademicYear seeders first.');
            return;
        }

        // Clear existing grading systems for this school/year to avoid duplicates if run multiple times
        GradingSystem::where('school_id', $school->id)
            ->where('academic_year_id', $academicYear->id)
            ->delete();

        // 1. CBSE Scholastic Grading System (9-point scale)
        $scholastic = GradingSystem::create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'CBSE Scholastic (9-Point Scale)',
            'type' => 'scholastic',
            'description' => 'Standard CBSE 9-point grading scale for scholastic subjects.',
        ]);

        $scholasticGrades = [
            ['name' => 'A1', 'min' => 91, 'max' => 100, 'gp' => 10.0, 'fail' => false, 'color' => '#1a7f37'],
            ['name' => 'A2', 'min' => 81, 'max' => 90,  'gp' => 9.0,  'fail' => false, 'color' => '#2da44e'],
            ['name' => 'B1', 'min' => 71, 'max' => 80,  'gp' => 8.0,  'fail' => false, 'color' => '#4ac26b'],
            ['name' => 'B2', 'min' => 61, 'max' => 70,  'gp' => 7.0,  'fail' => false, 'color' => '#80ccff'],
            ['name' => 'C1', 'min' => 51, 'max' => 60,  'gp' => 6.0,  'fail' => false, 'color' => '#54aeff'],
            ['name' => 'C2', 'min' => 41, 'max' => 50,  'gp' => 5.0,  'fail' => false, 'color' => '#0969da'],
            ['name' => 'D',  'min' => 33, 'max' => 40,  'gp' => 4.0,  'fail' => false, 'color' => '#f0883e'],
            ['name' => 'E1', 'min' => 21, 'max' => 32,  'gp' => 0.0,  'fail' => true,  'color' => '#da3633'],
            ['name' => 'E2', 'min' => 0,  'max' => 20,  'gp' => 0.0,  'fail' => true,  'color' => '#b91c1c'],
        ];

        foreach ($scholasticGrades as $g) {
            $scholastic->grades()->create([
                'school_id'      => $school->id,
                'name'           => $g['name'],
                'min_percentage' => $g['min'],
                'max_percentage' => $g['max'],
                'grade_point'    => $g['gp'],
                'is_fail'        => $g['fail'],
                'color_code'     => $g['color'],
            ]);
        }

        // 2. CBSE Co-Scholastic Grading System (3-point scale - Primary/Middle)
        $coScholastic3Point = GradingSystem::create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'CBSE Co-Scholastic (3-Point Scale)',
            'type' => 'co_scholastic',
            'description' => 'CBSE 3-point grading scale (Outstanding, Very Good, Fair) for co-scholastic areas.',
        ]);

        $co3PointGrades = [
            ['name' => 'A', 'min' => 67, 'max' => 100, 'gp' => 3.0, 'fail' => false, 'desc' => 'Outstanding', 'color' => '#2da44e'],
            ['name' => 'B', 'min' => 34, 'max' => 66,  'gp' => 2.0, 'fail' => false, 'desc' => 'Very Good',   'color' => '#54aeff'],
            ['name' => 'C', 'min' => 0,  'max' => 33,  'gp' => 1.0, 'fail' => false, 'desc' => 'Fair',        'color' => '#f0883e'],
        ];

        foreach ($co3PointGrades as $g) {
            $coScholastic3Point->grades()->create([
                'school_id'      => $school->id,
                'name'           => $g['name'],
                'min_percentage' => $g['min'],
                'max_percentage' => $g['max'],
                'grade_point'    => $g['gp'],
                'is_fail'        => $g['fail'],
                'description'    => $g['desc'],
                'color_code'     => $g['color'],
            ]);
        }

        // 3. CBSE Co-Scholastic Grading System (5-point scale - Secondary)
        $coScholastic5Point = GradingSystem::create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'CBSE Co-Scholastic (5-Point Scale)',
            'type' => 'co_scholastic',
            'description' => 'CBSE 5-point grading scale (A to E) for co-scholastic areas.',
        ]);

        $co5PointGrades = [
            ['name' => 'A', 'min' => 81, 'max' => 100, 'gp' => 5.0, 'fail' => false, 'color' => '#1a7f37'],
            ['name' => 'B', 'min' => 61, 'max' => 80,  'gp' => 4.0, 'fail' => false, 'color' => '#2da44e'],
            ['name' => 'C', 'min' => 41, 'max' => 60,  'gp' => 3.0, 'fail' => false, 'color' => '#54aeff'],
            ['name' => 'D', 'min' => 33, 'max' => 40,  'gp' => 2.0, 'fail' => false, 'color' => '#f0883e'],
            ['name' => 'E', 'min' => 0,  'max' => 32,  'gp' => 1.0, 'fail' => true,  'color' => '#da3633'],
        ];

        foreach ($co5PointGrades as $g) {
            $coScholastic5Point->grades()->create([
                'school_id'      => $school->id,
                'name'           => $g['name'],
                'min_percentage' => $g['min'],
                'max_percentage' => $g['max'],
                'grade_point'    => $g['gp'],
                'is_fail'        => $g['fail'],
                'color_code'     => $g['color'],
            ]);
        }

        $this->command->info('CBSE Grading Systems seeded successfully.');
    }
}
