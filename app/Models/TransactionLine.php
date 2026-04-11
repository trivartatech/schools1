<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionLine extends Model
{
    protected $fillable = [
        'transaction_id',
        'ledger_id',
        'type',         // debit | credit
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class);
    }
}
