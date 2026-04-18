<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreTransaction extends Model
{
    protected $fillable = [
        'school_id', 'store_id', 'item_id', 'type', 'quantity',
        'reference', 'notes', 'created_by', 'transaction_date',
    ];

    protected $casts = [
        'quantity'         => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(ItemStore::class, 'store_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StoreItem::class, 'item_id');
    }
}
