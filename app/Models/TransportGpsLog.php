<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportGpsLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'vehicle_id',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'timestamp',
        'created_at',
    ];

    protected $casts = [
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
        'speed'     => 'decimal:2',
        'timestamp' => 'datetime',
        'created_at'=> 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(TransportVehicle::class, 'vehicle_id');
    }
}
