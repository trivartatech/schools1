<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreItem extends Model
{
    protected $fillable = [
        'school_id', 'store_id', 'supplier_id', 'name', 'unit',
        'quantity', 'min_quantity', 'unit_price', 'notes',
    ];

    protected $casts = [
        'quantity'     => 'decimal:2',
        'min_quantity' => 'decimal:2',
        'unit_price'   => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(ItemStore::class, 'store_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StoreTransaction::class, 'item_id');
    }

    public function isLowStock(): bool
    {
        return $this->min_quantity > 0 && $this->quantity <= $this->min_quantity;
    }
}
