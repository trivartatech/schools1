<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HostelRollCall extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'hostel_id', 'student_id', 'date', 'status', 'slot', 'remarks', 'marked_by',
    ];

    public function school()   { return $this->belongsTo(School::class); }
    public function hostel()   { return $this->belongsTo(Hostel::class); }
    public function student()  { return $this->belongsTo(Student::class); }
    public function marker()   { return $this->belongsTo(User::class, 'marked_by'); }
}
