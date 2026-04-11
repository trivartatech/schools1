<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FeeGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['school_id', 'name', 'description'];

    public function feeHeads()
    {
        return $this->hasMany(FeeHead::class);
    }
}
