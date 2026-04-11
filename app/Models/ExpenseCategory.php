<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Ledger;

class ExpenseCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'school_id',
        'name',
        'description',
        'ledger_id',
    ];

    public function ledger()
    {
        return $this->belongsTo(Ledger::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
