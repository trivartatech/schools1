<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerType extends Model
{
    protected $fillable = [
        'school_id',
        'name',
        'nature',       // debit | credit
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function ledgers()
    {
        return $this->hasMany(Ledger::class);
    }

    // ── Scopes ────────────────────────────────────────────────
    public function scopeTenant($query)
    {
        return $query->where('school_id', app('current_school_id'));
    }
}
