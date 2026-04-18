<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemStore extends Model
{
    protected $fillable = [
        'school_id', 'name', 'location', 'incharge_staff_id', 'description',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StoreItem::class, 'store_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StoreTransaction::class, 'store_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'store_id');
    }
}
