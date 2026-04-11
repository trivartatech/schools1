<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialDownload extends Model
{
    protected $fillable = [
        'learning_material_id',
        'student_id',
        'downloaded_at',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];

    public function material() { return $this->belongsTo(LearningMaterial::class, 'learning_material_id'); }
    public function student()  { return $this->belongsTo(Student::class); }
}
