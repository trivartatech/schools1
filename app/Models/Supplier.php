<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'school_id', 'name', 'contact_person', 'phone', 'email',
        'gstin', 'address', 'city', 'state', 'website', 'notes',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function storeItems(): HasMany
    {
        return $this->hasMany(StoreItem::class);
    }
}
