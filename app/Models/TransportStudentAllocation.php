<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransportStudentAllocation extends Model
{
    use HasFactory;

    protected $table = 'transport_student_allocation';

    protected $fillable = [
        'school_id',
        'student_id',
        'route_id',
        'stop_id',
        'vehicle_id',
        'transport_fee',
        'fee_payment_id',
        'pickup_type',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'transport_fee' => 'decimal:2',
        'start_date'    => 'date',
        'end_date'      => 'date',
    ];

    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function route()
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }

    public function stop()
    {
        return $this->belongsTo(TransportStop::class, 'stop_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(TransportVehicle::class, 'vehicle_id');
    }
}
