<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransportRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'route_name',
        'route_code',
        'start_location',
        'end_location',
        'distance',
        'estimated_time',
        'status',
    ];

    protected $casts = [
        'distance' => 'decimal:2',
    ];

    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function stops()
    {
        return $this->hasMany(TransportStop::class, 'route_id')->orderBy('stop_order');
    }

    public function vehicles()
    {
        return $this->hasMany(TransportVehicle::class, 'route_id');
    }

    public function studentAllocations()
    {
        return $this->hasMany(TransportStudentAllocation::class, 'route_id');
    }
}
