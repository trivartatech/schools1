<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\AiTool;
use Illuminate\Support\Facades\DB;

class GetExamScheduleTool extends AiTool
{
    public function name(): string
    {
        return 'get_exam_schedule';
    }

    public function description(): string
    {
        return 'List upcoming exam papers (subject-level schedules) for the current academic year.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'days_ahead' => ['type' => 'integer', 'description' => 'Look ahead this many days (default 30, set 0 for "all upcoming")'],
                'limit'      => ['type' => 'integer', 'description' => 'Maximum results (default 25, max 100)'],
            ],
            'required' => [],
        ];
    }

    public function run(array $args): array
    {
        $days  = (int) ($args['days_ahead'] ?? 30);
        $limit = max(1, min(100, (int) ($args['limit'] ?? 25)));
        $today = now()->toDateString();

        $query = DB::table('exam_schedule_subjects as ess')
            ->join('exam_schedules as es', 'es.id', '=', 'ess.exam_schedule_id')
            ->leftJoin('exam_types as et',  'et.id', '=', 'es.exam_type_id')
            ->leftJoin('course_classes as c','c.id', '=', 'es.course_class_id')
            ->leftJoin('subjects as sub',   'sub.id', '=', 'ess.subject_id')
            ->where('es.school_id', $this->schoolId())
            ->when($this->academicYearId(), fn($q) => $q->where('es.academic_year_id', $this->academicYearId()))
            ->whereNotNull('ess.exam_date')
            ->where('ess.exam_date', '>=', $today);

        if ($days > 0) {
            $query->where('ess.exam_date', '<=', now()->addDays($days)->toDateString());
        }

        $rows = $query->orderBy('ess.exam_date')
            ->limit($limit)
            ->select(
                'ess.id',
                'ess.exam_date',
                'ess.exam_time',
                'et.name as exam_type',
                'c.name as class',
                'sub.name as subject'
            )
            ->get();

        return [
            'count'    => $rows->count(),
            'schedule' => $rows->map(fn($r) => [
                'date'      => $r->exam_date,
                'time'      => $r->exam_time,
                'exam_type' => $r->exam_type,
                'class'     => $r->class,
                'subject'   => $r->subject,
            ])->toArray(),
        ];
    }
}
