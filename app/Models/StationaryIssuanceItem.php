<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StationaryIssuanceItem extends Model
{
    use HasFactory;

    protected $table = 'stationary_issuance_items';

    protected $fillable = [
        'issuance_id', 'allocation_item_id', 'item_id', 'qty_issued',
    ];

    protected $casts = [
        'qty_issued' => 'integer',
    ];

    public function issuance()       { return $this->belongsTo(StationaryIssuance::class, 'issuance_id'); }
    public function allocationItem() { return $this->belongsTo(StationaryAllocationItem::class, 'allocation_item_id'); }
    public function item()           { return $this->belongsTo(StationaryItem::class, 'item_id'); }
}
