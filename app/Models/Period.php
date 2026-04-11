<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Period extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'name', 'start_time', 'end_time', 'type', 'is_weekend', 'order',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
