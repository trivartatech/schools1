<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BackupLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'created_by',
        'filename',
        'label',
        'status',
        'size_bytes',
        'duration_seconds',
        'error_message',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function scopeTenant($query)
    {
        return $query->where('school_id', app('current_school_id'));
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size_bytes ?? 0;
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    public function getFilePath(): string
    {
        return storage_path("app/backups/{$this->school_id}/{$this->filename}");
    }

    public function fileExists(): bool
    {
        return (bool) ($this->filename && file_exists($this->getFilePath()));
    }
}
