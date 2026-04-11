<?php

namespace App\Services;

use App\Models\School;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\CourseClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\ClassSubject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcademicRolloverService
{
    /**
     * Executes the rollover copy process.
     * 
     * @param School $school The school context
     * @param AcademicYear $sourceYear The year to copy FROM
     * @param AcademicYear $targetYear The year to copy TO
     * @param array $modules Which parts to copy (e.g., ['departments', 'classes', 'subjects'])
     */
    public function execute(School $school, AcademicYear $sourceYear, AcademicYear $targetYear, array $modules)
    {
        DB::beginTransaction();

        try {
            // Keep track of old IDs to new IDs mappings for relations
            $mapping = [
                'departments' => [],
                'classes'     => [],
                'sections'    => [],
                'subjects'    => [],
            ];

            if (in_array('departments', $modules)) {
                $mapping['departments'] = $this->cloneDepartments($school, $sourceYear, $targetYear);
            }

            if (in_array('classes', $modules)) {
                $classMap = $this->cloneClasses($school, $sourceYear, $targetYear, $mapping['departments']);
                $mapping['classes'] = $classMap['classes'];
                $mapping['sections'] = $classMap['sections'];
            }

            if (in_array('subjects', $modules)) {
                $mapping['subjects'] = $this->cloneSubjects($school, $sourceYear, $targetYear);
                
                // If we also cloned classes, we can clone the assignments
                if (in_array('classes', $modules)) {
                    $this->cloneClassSubjectAssignments(
                        $school, 
                        $sourceYear, 
                        $targetYear, 
                        $mapping['classes'], 
                        $mapping['sections'], 
                        $mapping['subjects']
                    );
                }
            }

            // We can easily expand this to handle Periods/Timetables, Fees, Transport, etc.

            DB::commit();
            
            return ['status' => 'success', 'message' => 'Rollover completed successfully.'];

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Rollover Failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    private function cloneDepartments(School $school, AcademicYear $sourceYear, AcademicYear $targetYear)
    {
        $map = [];
        $departments = Department::where('school_id', $school->id)->get();

        foreach ($departments as $dept) {
            $newDept = $dept->replicate();
            $newDept->academic_year_id = $targetYear->id;
            $newDept->save();

            $map[$dept->id] = $newDept->id;
        }

        return $map;
    }

    private function cloneClasses(School $school, AcademicYear $sourceYear, AcademicYear $targetYear, array $deptMap)
    {
        $classMap = [];
        $sectionMap = [];
        
        // Eager load sections to clone them inside the same loop
        $classes = CourseClass::with('sections')->where('school_id', $school->id)->where('academic_year_id', $sourceYear->id)->get();

        foreach ($classes as $cls) {
            $newClass = $cls->replicate();
            $newClass->academic_year_id = $targetYear->id;
            
            // Map to the newly created department if it exists, otherwise leave null
            if ($cls->department_id && isset($deptMap[$cls->department_id])) {
                $newClass->department_id = $deptMap[$cls->department_id];
            } else {
                $newClass->department_id = null; // Important: Clear it if we didn't clone departments to prevent orphan links
            }
            
            $newClass->save();
            $classMap[$cls->id] = $newClass->id;

            // Clone sections for this class
            foreach ($cls->sections as $section) {
                // Ensure the section actually belonged to the source year 
                if ($section->academic_year_id === $sourceYear->id) {
                    $newSection = $section->replicate();
                    $newSection->academic_year_id = $targetYear->id;
                    $newSection->course_class_id = $newClass->id;
                    $newSection->save();

                    $sectionMap[$section->id] = $newSection->id;
                }
            }
        }

        return ['classes' => $classMap, 'sections' => $sectionMap];
    }

    private function cloneSubjects(School $school, AcademicYear $sourceYear, AcademicYear $targetYear)
    {
        $map = [];
        $subjects = Subject::where('school_id', $school->id)->where('academic_year_id', $sourceYear->id)->get();

        foreach ($subjects as $sub) {
            $newSub = $sub->replicate();
            $newSub->academic_year_id = $targetYear->id;
            $newSub->save();

            $map[$sub->id] = $newSub->id;
        }

        return $map;
    }

    private function cloneClassSubjectAssignments(School $school, AcademicYear $sourceYear, AcademicYear $targetYear, array $classMap, array $sectionMap, array $subjectMap)
    {
        $assignments = ClassSubject::with(['courseClass', 'subject', 'section'])
            ->whereHas('courseClass', function($q) use ($sourceYear, $school) {
                $q->where('school_id', $school->id)->where('academic_year_id', $sourceYear->id);
            })->get();

        foreach ($assignments as $assignment) {
            // Only proceed if we actually mapped the class and subject
            if (isset($classMap[$assignment->course_class_id]) && isset($subjectMap[$assignment->subject_id])) {
                $mappedSectionId = null;
                if ($assignment->section_id && isset($sectionMap[$assignment->section_id])) {
                    $mappedSectionId = $sectionMap[$assignment->section_id];
                }

                ClassSubject::firstOrCreate([
                    'course_class_id' => $classMap[$assignment->course_class_id],
                    'section_id'      => $mappedSectionId,
                    'subject_id'      => $subjectMap[$assignment->subject_id],
                ], [
                    'is_co_scholastic' => $assignment->is_co_scholastic
                ]);
            }
        }
    }
}
