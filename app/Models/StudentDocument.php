<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'student_id',
        'document_type',
        'title',
        'is_original_submitted',
        'original_file_location',
        'file_path',
        'uploaded_by',
    ];

    protected $casts = [
        'is_original_submitted' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
