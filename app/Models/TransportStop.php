<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransportStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'route_id',
        'stop_name',
        'stop_code',
        'pickup_time',
        'drop_time',
        'distance_from_school',
        'fee',
        'stop_order',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'fee'                  => 'decimal:2',
        'distance_from_school' => 'decimal:2',
        'latitude'             => 'decimal:7',
        'longitude'            => 'decimal:7',
    ];

    public function scopeTenant($query)
    {
        return $query->where($this->getTable() . '.school_id', app('current_school_id'));
    }

    public function route()
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }

    public function studentAllocations()
    {
        return $this->hasMany(TransportStudentAllocation::class, 'stop_id');
    }
}
