<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyllabusStatus extends Model
{
    protected $table = 'syllabus_status';

    protected $fillable = [
        'topic_id',
        'section_id',
        'teacher_id',
        'status',
        'planned_date',
        'completed_date'
    ];

    protected $casts = [
        'planned_date' => 'date',
        'completed_date' => 'date'
    ];

    public function topic()
    {
        return $this->belongsTo(SyllabusTopic::class, 'topic_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Staff::class, 'teacher_id');
    }
}
