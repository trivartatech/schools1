<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StationaryReturnItem extends Model
{
    use HasFactory;

    protected $table = 'stationary_return_items';

    protected $fillable = [
        'return_id', 'allocation_item_id', 'item_id',
        'qty_returned', 'condition', 'restock',
        'refund_unit_price', 'line_refund',
    ];

    protected $casts = [
        'qty_returned'      => 'integer',
        'restock'           => 'boolean',
        'refund_unit_price' => 'decimal:2',
        'line_refund'       => 'decimal:2',
    ];

    public function return()         { return $this->belongsTo(StationaryReturn::class, 'return_id'); }
    public function allocationItem() { return $this->belongsTo(StationaryAllocationItem::class, 'allocation_item_id'); }
    public function item()           { return $this->belongsTo(StationaryItem::class, 'item_id'); }
}
