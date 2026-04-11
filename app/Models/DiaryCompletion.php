<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiaryCompletion extends Model
{
    protected $fillable = ['diary_id', 'student_id', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime'];

    public function diary()   { return $this->belongsTo(StudentDiary::class); }
    public function student() { return $this->belongsTo(Student::class); }
}
