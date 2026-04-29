<?php

namespace App\Services\Ai\Tools;

use App\Models\Student;
use App\Services\Ai\AiTool;
use Illuminate\Support\Facades\DB;

class SearchStudentByNameTool extends AiTool
{
    public function name(): string
    {
        return 'search_student_by_name';
    }

    public function description(): string
    {
        return 'Search for active students by name (matches first name, last name, or both). Use when the user asks for a student by name.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Partial name or admission number to search for'],
                'limit' => ['type' => 'integer', 'description' => 'Maximum number of results to return (default 10, max 25)'],
            ],
            'required' => ['query'],
        ];
    }

    public function run(array $args): array
    {
        $query = trim((string) ($args['query'] ?? ''));
        $limit = max(1, min(25, (int) ($args['limit'] ?? 10)));

        if ($query === '') {
            return ['matches' => []];
        }

        $like = '%' . $query . '%';

        $rows = Student::where('students.school_id', $this->schoolId())
            ->where('students.status', 'active')
            ->enrolledInYear($this->academicYearId())
            ->where(function ($q) use ($like) {
                $q->where('first_name', 'like', $like)
                  ->orWhere('last_name', 'like', $like)
                  ->orWhere('admission_no', 'like', $like)
                  ->orWhereRaw("(first_name || ' ' || last_name) LIKE ?", [$like]);
            })
            ->leftJoin('student_academic_histories as h', function ($j) {
                $j->on('h.student_id', '=', 'students.id')
                  ->when($this->academicYearId(), fn($q) => $q->where('h.academic_year_id', $this->academicYearId()));
            })
            ->leftJoin('course_classes as c', 'c.id', '=', 'h.class_id')
            ->leftJoin('sections as s',       's.id', '=', 'h.section_id')
            ->select(
                'students.id',
                'students.first_name',
                'students.last_name',
                'students.admission_no',
                'c.name as class',
                's.name as section'
            )
            ->limit($limit)
            ->get();

        return [
            'count'   => $rows->count(),
            'matches' => $rows->map(fn($r) => [
                'id'           => $r->id,
                'name'         => trim($r->first_name . ' ' . $r->last_name),
                'admission_no' => $r->admission_no,
                'class'        => $r->class,
                'section'      => $r->section,
            ])->toArray(),
        ];
    }
}
