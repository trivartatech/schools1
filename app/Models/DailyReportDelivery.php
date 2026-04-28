<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReportDelivery extends Model
{
    protected $fillable = [
        'school_id',
        'admin_contact_id',
        'report_date',
        'mode',
        'channel_used',
        'to_number',
        'sent_at',
        'error',
        'pdf_path',
    ];

    protected $casts = [
        'report_date' => 'date',
        'sent_at'     => 'datetime',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function adminContact()
    {
        return $this->belongsTo(AdminContact::class);
    }
}
