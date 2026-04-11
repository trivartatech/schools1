<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudentParent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'parents';

    protected $fillable = [
        'school_id', 'user_id', 'father_name', 'mother_name', 'guardian_name',
        'father_phone', 'mother_phone', 'primary_phone', 'guardian_email', 'guardian_phone',
        'father_occupation', 'father_qualification', 'mother_occupation', 'mother_qualification',
        'address'
    ];

    public function students()
    {
        return $this->hasMany(Student::class, 'parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
