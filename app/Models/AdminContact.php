<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminContact extends Model
{
    protected $fillable = [
        'school_id',
        'name',
        'phone',
        'whatsapp_number',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
