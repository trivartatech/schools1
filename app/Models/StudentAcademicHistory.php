<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentAcademicHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'student_id', 'academic_year_id',
        'class_id', 'section_id', 'roll_no', 'status', 'enrollment_type', 'student_type', 'remarks'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function courseClass()
    {
        return $this->belongsTo(CourseClass::class, 'class_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Resolve a student's effective fee-rule classification ('new' | 'old').
     *
     * Priority:
     *   1. Honour an explicit `student_type` on the current academic history
     *      row when it clearly says 'old' or 'new' (handles 'Old Student',
     *      'New Student', 'old', 'NEW', mixed case). This is the override
     *      path admins can drive from the Record Detail modal — useful for
     *      transfer-ins, repeats, or legacy imports where the count-based
     *      heuristic would lie.
     *   2. Otherwise fall back to: more than 1 academic history row → 'old',
     *      else 'new'. This is the original "promoted at least once"
     *      heuristic kept for students whose row hasn't been explicitly
     *      tagged.
     *
     * Anything not matching old/new (empty, 'Repeating', etc.) falls
     * through to the count rule, so unknown labels degrade safely.
     */
    public static function resolveStudentType(?string $explicitLabel, int $historyCount): string
    {
        $label = strtolower((string) $explicitLabel);
        if ($label !== '') {
            if (str_contains($label, 'old')) return 'old';
            if (str_contains($label, 'new')) return 'new';
        }
        return $historyCount > 1 ? 'old' : 'new';
    }
}
