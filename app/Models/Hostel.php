<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hostel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'name',
        'type',
        'address',
        'intake_capacity',
        'description',
        'warden_id',
        'blocks',
        'floors',
        'room_types',
    ];

    protected $casts = [
        'blocks' => 'array',
        'floors' => 'array',
        'room_types' => 'array',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function warden()
    {
        return $this->belongsTo(User::class, 'warden_id');
    }

    public function rooms()
    {
        return $this->hasMany(HostelRoom::class);
    }
}
