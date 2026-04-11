<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PostMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'file_path',
        'original_name',
        'mime_type',
        'thumbnail_path',
        'sort_order',
    ];

    protected $appends = ['url', 'thumbnail_url', 'is_video'];

    // ── Accessors ──────────────────────────────────────────────────

    public function getUrlAttribute()
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    public function getThumbnailUrlAttribute()
    {
        return $this->thumbnail_path 
            ? Storage::disk('public')->url($this->thumbnail_path) 
            : $this->url;
    }

    public function getIsVideoAttribute()
    {
        return str_starts_with($this->mime_type ?? '', 'video/');
    }

    // ── Relationships ──────────────────────────────────────────────

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
