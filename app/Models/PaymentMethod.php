<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'school_id',
        'code',
        'label',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public static function defaultsForSchool(int $schoolId): array
    {
        return [
            ['code' => 'cash',          'label' => 'Cash',          'sort_order' => 1],
            ['code' => 'cheque',        'label' => 'Cheque',        'sort_order' => 2],
            ['code' => 'online',        'label' => 'Online',        'sort_order' => 3],
            ['code' => 'upi',           'label' => 'UPI',           'sort_order' => 4],
            ['code' => 'card',          'label' => 'Card',          'sort_order' => 5],
            ['code' => 'dd',            'label' => 'Demand Draft',  'sort_order' => 6],
            ['code' => 'neft',          'label' => 'NEFT',          'sort_order' => 7],
            ['code' => 'rtgs',          'label' => 'RTGS',          'sort_order' => 8],
            ['code' => 'bank_transfer', 'label' => 'Bank Transfer', 'sort_order' => 9],
        ];
    }
}
