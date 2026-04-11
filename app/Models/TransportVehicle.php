<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransportVehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'vehicle_number',
        'vehicle_name',
        'driver_id',
        'conductor_name',
        'capacity',
        'route_id',
        'gps_device_id',
        'insurance_expiry',
        'fitness_expiry',
        'pollution_expiry',
        'status',
    ];

    protected $casts = [
        'insurance_expiry'  => 'date',
        'fitness_expiry'    => 'date',
        'pollution_expiry'  => 'date',
    ];

    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /** Driver is a staff member with designation = Driver */
    public function driver()
    {
        return $this->belongsTo(Staff::class, 'driver_id');
    }

    public function route()
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }

    public function liveLocation()
    {
        return $this->hasOne(TransportVehicleLiveLocation::class, 'vehicle_id');
    }

    public function gpsLogs()
    {
        return $this->hasMany(TransportGpsLog::class, 'vehicle_id');
    }

    public function studentAllocations()
    {
        return $this->hasMany(TransportStudentAllocation::class, 'vehicle_id');
    }
}
