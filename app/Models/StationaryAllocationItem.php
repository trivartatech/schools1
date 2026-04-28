<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StationaryAllocationItem extends Model
{
    use HasFactory;

    protected $table = 'stationary_allocation_items';

    protected $fillable = [
        'allocation_id', 'item_id',
        'qty_entitled', 'qty_collected',
        'unit_price', 'line_total',
    ];

    protected $casts = [
        'qty_entitled'  => 'integer',
        'qty_collected' => 'integer',
        'unit_price'    => 'decimal:2',
        'line_total'    => 'decimal:2',
    ];

    public function allocation()       { return $this->belongsTo(StationaryStudentAllocation::class, 'allocation_id'); }
    public function item()             { return $this->belongsTo(StationaryItem::class, 'item_id'); }
    public function issuanceLines()    { return $this->hasMany(StationaryIssuanceItem::class, 'allocation_item_id'); }
    public function returnLines()      { return $this->hasMany(StationaryReturnItem::class, 'allocation_item_id'); }

    public function getQtyRemainingAttribute(): int
    {
        return max(0, (int) $this->qty_entitled - (int) $this->qty_collected);
    }
}
