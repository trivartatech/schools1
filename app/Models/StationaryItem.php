<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StationaryItem extends Model
{
    use HasFactory;

    protected $table = 'stationary_items';

    protected $fillable = [
        'school_id', 'name', 'code', 'unit_price', 'hsn_code',
        'current_stock', 'min_stock', 'status', 'description',
    ];

    protected $casts = [
        'unit_price'    => 'decimal:2',
        'current_stock' => 'integer',
        'min_stock'     => 'integer',
    ];

    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    public function school()           { return $this->belongsTo(School::class); }
    public function allocationItems()  { return $this->hasMany(StationaryAllocationItem::class, 'item_id'); }

    public function isLowStock(): bool
    {
        return $this->current_stock <= $this->min_stock;
    }

    public function decrementStock(int $qty): void
    {
        $this->decrement('current_stock', $qty);
    }

    public function incrementStock(int $qty): void
    {
        $this->increment('current_stock', $qty);
    }
}
