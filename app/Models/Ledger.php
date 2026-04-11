<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ledger extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id',
        'ledger_type_id',
        'name',
        'code',
        'opening_balance',
        'opening_balance_type',  // debit | credit
        'description',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'opening_balance'       => 'decimal:2',
        'is_system'             => 'boolean',
        'is_active'             => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function ledgerType()
    {
        return $this->belongsTo(LedgerType::class);
    }

    public function transactionLines()
    {
        return $this->hasMany(TransactionLine::class);
    }

    // ── Current Balance ───────────────────────────────────────
    /**
     * Returns ['amount' => float, 'type' => 'debit'|'credit']
     * Considers opening balance + all transaction lines.
     */
    public function getCurrentBalance(): array
    {
        $lines = $this->transactionLines()
            ->selectRaw("type, SUM(amount) as total")
            ->groupBy('type')
            ->pluck('total', 'type');

        $txDebit  = (float) ($lines['debit']  ?? 0);
        $txCredit = (float) ($lines['credit'] ?? 0);

        $openDebit  = $this->opening_balance_type === 'debit'  ? (float) $this->opening_balance : 0;
        $openCredit = $this->opening_balance_type === 'credit' ? (float) $this->opening_balance : 0;

        $totalDebit  = $openDebit  + $txDebit;
        $totalCredit = $openCredit + $txCredit;

        $net  = $totalDebit - $totalCredit;
        $type = $net >= 0 ? 'debit' : 'credit';

        return ['amount' => round(abs($net), 2), 'type' => $type];
    }

    // ── Scopes ────────────────────────────────────────────────
    public function scopeTenant($query)
    {
        return $query->where('school_id', app('current_school_id'));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
