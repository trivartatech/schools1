<?php

namespace App\Contracts;

use App\Models\Student;

interface FeeServiceContract
{
    /**
     * Get a comprehensive fee summary for a student in a specific academic year.
     *
     * @param Student  $student
     * @param int      $academicYearId
     * @param int|null $schoolId
     * @param array    $preloaded  Optional pre-loaded relations to skip DB queries
     */
    public function getStudentFeeSummary(
        Student $student,
        int $academicYearId,
        ?int $schoolId = null,
        array $preloaded = []
    ): array;
}
