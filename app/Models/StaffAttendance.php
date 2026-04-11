<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffAttendance extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'school_id',
        'staff_id',
        'date',
        'status',
        'check_in',
        'check_out',
        'punch_in_lat',
        'punch_in_lng',
        'punch_out_lat',
        'punch_out_lng',
        'remarks',
        'marked_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function scopeTenant($query)
    {
        return $query->where('school_id', app('current_school_id'));
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function marker()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
