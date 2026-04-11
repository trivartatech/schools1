<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportAttendance extends Model
{
    protected $table = 'transport_attendance';

    protected $fillable = [
        'school_id', 'student_id', 'route_id', 'vehicle_id', 'stop_id',
        'date', 'trip_type', 'status', 'boarded_at', 'alighted_at',
        'marked_by', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function scopeTenant($query)
    {
        return $query->where($this->getTable() . '.school_id', app('current_school_id'));
    }

    public function student()  { return $this->belongsTo(Student::class); }
    public function route()    { return $this->belongsTo(TransportRoute::class, 'route_id'); }
    public function vehicle()  { return $this->belongsTo(TransportVehicle::class, 'vehicle_id'); }
    public function stop()     { return $this->belongsTo(TransportStop::class, 'stop_id'); }
    public function markedBy() { return $this->belongsTo(User::class, 'marked_by'); }
}
