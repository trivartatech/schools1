<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HostelRoom extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'hostel_id',
        'block_name',
        'floor_name',
        'room_number',
        'capacity',
        'room_type',
        'cost_per_month',
        'status',
        'description',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function hostel()
    {
        return $this->belongsTo(Hostel::class);
    }

    public function beds()
    {
        return $this->hasMany(HostelBed::class);
    }
}
