<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnlineClass extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'class_id', 'section_id', 'subject_id', 'teacher_id',
        'start_time', 'end_time', 'meeting_link', 'platform', 'recording_link',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
    ];

    public function school()      { return $this->belongsTo(School::class); }
    public function courseClass() { return $this->belongsTo(CourseClass::class, 'class_id'); }
    public function section()     { return $this->belongsTo(Section::class); }
    public function subject()     { return $this->belongsTo(Subject::class); }
    public function teacher()     { return $this->belongsTo(Staff::class, 'teacher_id'); }

    /** True if class starts within 15 minutes or is ongoing. */
    public function getIsJoinableAttribute(): bool
    {
        if (!$this->start_time) return false;
        $now = now();
        return $this->start_time->diffInMinutes($now, false) <= 15
            && ($this->end_time === null || $this->end_time->gt($now));
    }
}
