<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationLog extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'type',
        'provider',
        'to',
        'message',
        'status',
        'provider_response'
    ];

    protected $casts = [
        'provider_response' => 'array'
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
