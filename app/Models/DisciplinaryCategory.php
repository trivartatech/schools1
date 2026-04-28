<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisciplinaryCategory extends Model
{
    protected $fillable = ['school_id', 'name', 'short_code', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
