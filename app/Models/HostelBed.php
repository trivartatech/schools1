<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HostelBed extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'hostel_room_id',
        'name',
        'status',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function room()
    {
        return $this->belongsTo(HostelRoom::class, 'hostel_room_id');
    }

    public function student()
    {
        return $this->hasOne(HostelStudent::class)->whereRaw('LOWER(status) = ?', ['active']);
    }
}
