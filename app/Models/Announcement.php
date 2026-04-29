<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'school_id',
        'sender_id',
        'title',
        'delivery_method',
        'audience_type',
        'audience_ids',
        'communication_template_id',
        'audio_path',
        'mp3_path',
        'scheduled_at',
        'is_broadcasted',
        'broadcast_error',
        'failed_at',
        'broadcast_stats',
    ];

    protected $casts = [
        'audience_ids'    => 'json',
        'scheduled_at'    => 'datetime',
        'is_broadcasted'  => 'boolean',
        'broadcast_error' => 'array',
        'failed_at'       => 'datetime',
        'broadcast_stats' => 'array',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function template()
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id');
    }
}
