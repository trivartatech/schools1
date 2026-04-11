<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Holiday extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'title', 'date', 'end_date', 'type', 'description',
    ];

    protected $casts = [
        'date'     => 'date',
        'end_date' => 'date',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
