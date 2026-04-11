<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'academic_year_id', 'student_id',
        'class_id', 'section_id', 'date', 'status',
        'remarks', 'marked_by',
    ];

    protected $casts = [
        // 'date' cast intentionally removed — MySQL DATE column stores Y-m-d natively.
        // Keeping cast caused Eloquent to reformat date, breaking WHERE date = 'Y-m-d' lookups.
    ];

    public function student()  { return $this->belongsTo(Student::class); }
    public function courseClass()  { return $this->belongsTo(CourseClass::class, 'class_id'); }
    public function section()  { return $this->belongsTo(Section::class); }
    public function markedBy() { return $this->belongsTo(User::class, 'marked_by'); }
}

