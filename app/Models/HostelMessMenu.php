<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HostelMessMenu extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'hostel_id',
        'day',
        'meal_type',
        'items',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function hostel()
    {
        return $this->belongsTo(Hostel::class);
    }
}
