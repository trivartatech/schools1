<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EditRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'user_id',
        'requestable_type',
        'requestable_id',
        'requested_changes',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'requested_changes' => 'array',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Scope a query to only include records for the current school.
     */
    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    /**
     * Get the parent requestable model (Student or Staff).
     */
    public function requestable()
    {
        return $this->morphTo();
    }

    /**
     * Get the user who submitted the request.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the admin who reviewed the request.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
