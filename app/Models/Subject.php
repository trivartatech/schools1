<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'name', 'code', 'part', 'subject_type_id',
        'type', 'is_elective', 'is_co_scholastic', 'sort_order',
    ];

    protected $casts = [
        'is_elective'      => 'boolean',
        'is_co_scholastic' => 'boolean',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function subjectType()
    {
        return $this->belongsTo(SubjectType::class);
    }

    public function classAssignments()
    {
        return $this->hasMany(ClassSubject::class);
    }
}
