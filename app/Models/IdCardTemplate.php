<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdCardTemplate extends Model
{
    protected $fillable = [
        'school_id', 'created_by', 'name',
        'orientation', 'background', 'elements', 'columns',
    ];

    protected $casts = [
        'background' => 'array',
        'elements'   => 'array',
        'columns'    => 'integer',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
