<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyllabusTopic extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'class_id', 'subject_id',
        'chapter_name', 'topic_name', 'sort_order',
    ];

    public function school()      { return $this->belongsTo(School::class); }
    public function courseClass() { return $this->belongsTo(CourseClass::class, 'class_id'); }
    public function subject()     { return $this->belongsTo(Subject::class); }
    public function statuses()    { return $this->hasMany(SyllabusStatus::class, 'topic_id'); }
}
