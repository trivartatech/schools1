<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiInsightView extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'name',
        'filters_json',
    ];

    protected $casts = [
        'filters_json' => 'array',
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
