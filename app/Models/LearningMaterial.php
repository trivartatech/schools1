<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningMaterial extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'class_id', 'section_id', 'subject_id', 'teacher_id',
        'title', 'description', 'type', 'file_path', 'external_url',
        'chapter_name', 'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function school()       { return $this->belongsTo(School::class); }
    public function courseClass()  { return $this->belongsTo(CourseClass::class, 'class_id'); }
    public function section()      { return $this->belongsTo(Section::class); }
    public function subject()      { return $this->belongsTo(Subject::class); }
    public function teacher()      { return $this->belongsTo(Staff::class, 'teacher_id'); }
    public function downloads()    { return $this->hasMany(MaterialDownload::class); }

    /** Detect YouTube / Vimeo / Google Drive from external_url */
    public function getEmbedTypeAttribute(): ?string
    {
        if (!$this->external_url) return null;
        if (str_contains($this->external_url, 'youtube.com') || str_contains($this->external_url, 'youtu.be')) return 'youtube';
        if (str_contains($this->external_url, 'vimeo.com')) return 'vimeo';
        if (str_contains($this->external_url, 'drive.google.com')) return 'gdrive';
        return 'link';
    }
}
