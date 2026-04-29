<?php

namespace App\Services\Ai\Tools;

use App\Models\Student;
use App\Services\Ai\AiTool;

class GetStudentDetailsTool extends AiTool
{
    public function name(): string
    {
        return 'get_student_details';
    }

    public function description(): string
    {
        return 'Get detailed information for a single student by id, including current class/section and admission number. Use after `search_student_by_name` to get more details.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'description' => 'The student id'],
            ],
            'required' => ['student_id'],
        ];
    }

    public function run(array $args): array
    {
        $id = (int) ($args['student_id'] ?? 0);
        if ($id < 1) return ['error' => 'student_id is required'];

        $row = Student::where('students.id', $id)
            ->where('students.school_id', $this->schoolId())
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
                'students.gender',
                'students.date_of_birth',
                'students.phone',
                'students.email',
                'students.status',
                'c.name as class',
                's.name as section'
            )
            ->first();

        if (!$row) return ['error' => 'Student not found'];

        return [
            'id'           => $row->id,
            'name'         => trim($row->first_name . ' ' . $row->last_name),
            'admission_no' => $row->admission_no,
            'gender'       => $row->gender,
            'date_of_birth'=> $row->date_of_birth,
            'class'        => $row->class,
            'section'      => $row->section,
            'phone'        => $row->phone,
            'email'        => $row->email,
            'status'       => $row->status,
        ];
    }
}
