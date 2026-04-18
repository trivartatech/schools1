<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes, HasActivityLog;

    protected $fillable = [
        'school_id', 'category_id', 'name', 'asset_code', 'brand', 'model_no', 'serial_no',
        'purchase_date', 'purchase_cost', 'supplier', 'supplier_id', 'store_id',
        'warranty_until', 'useful_life_years',
        'depreciation_method', 'condition', 'status', 'notes', 'disposed_on', 'disposal_reason',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'disposed_on'   => 'date',
    ];

    public function category()         { return $this->belongsTo(AssetCategory::class, 'category_id'); }
    public function assignments()      { return $this->hasMany(AssetAssignment::class); }
    public function activeAssignment() { return $this->hasOne(AssetAssignment::class)->whereNull('returned_on'); }
    public function maintenanceLogs()  { return $this->hasMany(AssetMaintenance::class); }
    public function supplierModel()    { return $this->belongsTo(Supplier::class, 'supplier_id'); }
    public function store()            { return $this->belongsTo(ItemStore::class, 'store_id'); }

    public function getCurrentValueAttribute(): float
    {
        if (!$this->purchase_date || !$this->purchase_cost || !$this->useful_life_years) {
            return (float) $this->purchase_cost;
        }
        $yearsOld = now()->diffInYears($this->purchase_date);
        $cost     = (float) $this->purchase_cost;

        if ($this->depreciation_method === 'declining_balance') {
            // Double declining balance
            $rate = 2 / $this->useful_life_years;
            return max(0, $cost * pow(1 - $rate, $yearsOld));
        }

        // Straight-line (default)
        return max(0, $cost - ($cost / $this->useful_life_years * $yearsOld));
    }

    public function getTotalMaintenanceCostAttribute(): float
    {
        return (float) $this->maintenanceLogs->sum('cost');
    }
}
