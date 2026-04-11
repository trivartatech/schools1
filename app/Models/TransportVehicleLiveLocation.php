<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportVehicleLiveLocation extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'vehicle_id';

    protected $fillable = [
        'vehicle_id',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'updated_at',
    ];

    protected $casts = [
        'latitude'   => 'decimal:7',
        'longitude'  => 'decimal:7',
        'speed'      => 'decimal:2',
        'updated_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(TransportVehicle::class, 'vehicle_id');
    }
}
