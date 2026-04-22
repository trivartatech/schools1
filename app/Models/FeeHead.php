<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FeeHead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id', 'fee_group_id', 'name', 'short_code',
        'description', 'is_taxable', 'gst_percent', 'sort_order',
        'is_hostel_fee',
    ];

    protected $casts = [
        'is_taxable'    => 'boolean',
        'is_hostel_fee' => 'boolean',
        'gst_percent'   => 'decimal:2',
    ];

    public function feeGroup()
    {
        return $this->belongsTo(FeeGroup::class);
    }

    public function structures()
    {
        return $this->hasMany(FeeStructure::class);
    }
}
