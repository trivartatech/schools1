<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\CourseClass;
use App\Models\Section;
use App\Models\Staff;
use Illuminate\Support\Facades\Cache;

/**
 * TeacherScopeService
 * ─────────────────────────────────────────────────────────────────────────────
 * Resolves a teacher's data scope from incharge assignments.
 *
 * Hierarchy (higher overrides lower):
 *   1. course_classes.incharge_staff_id   → Class teacher: ALL sections + ALL subjects
 *   2. sections.incharge_staff_id         → Section teacher: ALL subjects in that section
 *   3. class_subjects.incharge_staff_id   → Subject teacher: ONLY that subject in that section
 *
 * Key scope properties:
 *   ->restricted        bool        — true for teachers (false = admin, see everything)
 *   ->subjectRestricted bool        — true ONLY if teacher is purely a subject incharge
 *                                     with no class/section incharge role
 *   ->staffId           int|null
 *   ->classIds          Collection  — class IDs the teacher has ANY access to
 *   ->sectionIds        Collection  — section IDs the teacher can access
 *   ->subjectIds        Collection  — ALL subject IDs they teach (across all sections)
 *   ->classSubjects     Collection  — raw ClassSubject records (for detailed mapping)
 *   ->allowedMap        array       — [class_id][section_id] = 'ALL' | [subject_id, ...]
 *                                     Mirrors the ExamMarkController pattern, reusable everywhere
 */
class TeacherScopeService
{
    public function for(\App\Models\User $user): object
    {
        // Admins, principals, school admins → no restrictions
        if (! $user->isTeacher()) {
            return $this->unrestricted();
        }

        $staff = Staff::where('user_id', $user->id)->first();
        if (! $staff) {
            return $this->emptyScope(); // no staff record = see nothing
        }

        $schoolId = $user->school_id;
        $cacheKey = "teacher_scope_{$staff->id}_{$schoolId}";

        return Cache::remember($cacheKey, 300, function () use ($staff, $schoolId) {
            $allowedMap = [];

            // ── 1. Class incharge → ALL sections + ALL subjects of that class ─────
            $classInchargeIds = CourseClass::where('school_id', $schoolId)
                ->where('incharge_staff_id', $staff->id)
                ->pluck('id');

            if ($classInchargeIds->isNotEmpty()) {
                $classeSections = Section::where('school_id', $schoolId)
                    ->whereIn('course_class_id', $classInchargeIds)
                    ->get();
                foreach ($classeSections as $sec) {
                    $allowedMap[$sec->course_class_id][$sec->id] = 'ALL';
                }
            }

            // ── 2. Section incharge → ALL subjects in their section ───────────────
            $sectionInchargeItems = Section::where('school_id', $schoolId)
                ->where('incharge_staff_id', $staff->id)
                ->get();

            foreach ($sectionInchargeItems as $sec) {
                // Only set to ALL if not already set (class incharge has higher priority)
                if (! isset($allowedMap[$sec->course_class_id][$sec->id])) {
                    $allowedMap[$sec->course_class_id][$sec->id] = 'ALL';
                }
            }

            // ── 3. Subject incharge → specific subject in a class+section ──────────
            $classSubjects = ClassSubject::with(['courseClass', 'section', 'subject'])
                ->where('school_id', $schoolId)
                ->where('incharge_staff_id', $staff->id)
                ->get();

            foreach ($classSubjects as $cs) {
                $classId   = $cs->course_class_id;
                $sectionId = $cs->section_id;

                if (! isset($allowedMap[$classId])) {
                    $allowedMap[$classId] = [];
                }

                if (! isset($allowedMap[$classId][$sectionId])) {
                    $allowedMap[$classId][$sectionId] = [];
                }

                // If already 'ALL' (from class/section incharge) → don't narrow it
                if ($allowedMap[$classId][$sectionId] !== 'ALL') {
                    $allowedMap[$classId][$sectionId][] = $cs->subject_id;
                }
            }

            // ── Derive flattened IDs from allowedMap ──────────────────────────────
            $classIds = collect(array_keys($allowedMap))->unique()->values();

            $sectionIds = collect();
            foreach ($allowedMap as $sections) {
                $sectionIds = $sectionIds->merge(array_keys($sections));
            }
            $sectionIds = $sectionIds->unique()->values();

            $subjectIds = $classSubjects->pluck('subject_id')->unique()->values();

            // Teacher is "subject restricted" only if they have NO class/section incharge role
            // i.e. every entry in allowedMap is an array (not 'ALL')
            $subjectRestricted = $classInchargeIds->isEmpty()
                && $sectionInchargeItems->isEmpty()
                && $classSubjects->isNotEmpty();

            return (object) [
                'restricted'        => true,
                'subjectRestricted' => $subjectRestricted,
                'staffId'           => $staff->id,
                'classIds'          => $classIds,
                'sectionIds'        => $sectionIds,
                'subjectIds'        => $subjectIds,
                'classSubjects'     => $classSubjects,
                'allowedMap'        => $allowedMap,
            ];
        });
    }

    /**
     * Apply scope to a query builder.
     * Call this from any controller to filter by teacher's assigned sections.
     * For subject filtering, use ->applySubjectScope() separately.
     */
    public function applySectionScope($query, object $scope, string $sectionColumn = 'section_id'): void
    {
        if ($scope->restricted) {
            $query->whereIn($sectionColumn, $scope->sectionIds->isEmpty() ? [-1] : $scope->sectionIds);
        }
    }

    /**
     * Returns subject IDs a teacher can see for a specific section.
     * Returns null if they can see ALL subjects (no restriction).
     * Returns [-1] (no subjects) if they have no access to the section.
     */
    public function allowedSubjectsForSection(object $scope, int $classId, int $sectionId): ?array
    {
        if (! $scope->restricted) {
            return null; // admin: no filter
        }

        if (! isset($scope->allowedMap[$classId][$sectionId])) {
            return [-1]; // no access at all
        }

        $entry = $scope->allowedMap[$classId][$sectionId];
        return $entry === 'ALL' ? null : $entry;
    }

    /** Clear cached scope (call after any incharge assignment change) */
    public function clearCache(int $staffId, int $schoolId): void
    {
        Cache::forget("teacher_scope_{$staffId}_{$schoolId}");
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function unrestricted(): object
    {
        return (object) [
            'restricted'        => false,
            'subjectRestricted' => false,
            'staffId'           => null,
            'classIds'          => collect(),
            'sectionIds'        => collect(),
            'subjectIds'        => collect(),
            'classSubjects'     => collect(),
            'allowedMap'        => [],
        ];
    }

    private function emptyScope(): object
    {
        return (object) [
            'restricted'        => true,
            'subjectRestricted' => false,
            'staffId'           => null,
            'classIds'          => collect(),
            'sectionIds'        => collect([-1]),
            'subjectIds'        => collect(),
            'classSubjects'     => collect(),
            'allowedMap'        => [],
        ];
    }
}
