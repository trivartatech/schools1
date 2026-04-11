<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'user_id',
        'content',
        'visibility',
        'type',
        'tags',
        'class_id',
        'is_approved',
        'is_pinned',
        'pinned_at',
        'pinned_by',
        'shares_count',
    ];

    protected $casts = [
        'is_approved'  => 'boolean',
        'is_pinned'    => 'boolean',
        'pinned_at'    => 'datetime',
        'tags'         => 'array',
        'shares_count' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function class()
    {
        return $this->belongsTo(CourseClass::class, 'class_id');
    }

    public function media()
    {
        return $this->hasMany(PostMedia::class)->orderBy('sort_order');
    }

    public function likes()
    {
        return $this->hasMany(PostLike::class);
    }

    public function comments()
    {
        return $this->hasMany(PostComment::class)->whereNull('parent_id');
    }

    public function allComments()
    {
        return $this->hasMany(PostComment::class);
    }

    public function bookmarks()
    {
        return $this->hasMany(PostBookmark::class);
    }

    public function pinnedByUser()
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isLikedBy(User $user)
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function isBookmarkedBy(User $user)
    {
        return $this->bookmarks()->where('user_id', $user->id)->exists();
    }
}
