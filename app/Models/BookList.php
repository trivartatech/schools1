<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookList extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'academic_year_id', 'class_id', 'subject_id',
        'book_name', 'publisher', 'author', 'isbn',
    ];

    public function school()       { return $this->belongsTo(School::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function courseClass()  { return $this->belongsTo(CourseClass::class, 'class_id'); }
    public function subject()      { return $this->belongsTo(Subject::class); }
}
