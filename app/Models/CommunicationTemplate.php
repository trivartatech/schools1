<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'type',
        'name',
        'slug',
        'template_id',
        'subject',
        'content',
        'audio_url',
        'is_active',
        'is_system',
        'variables',
        'language_code'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'variables' => 'json'
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
