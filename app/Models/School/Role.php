<?php

namespace App\Models\School;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Role extends Model
{
    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'roles';

    public function school(): BelongsTo
    {
        return $this->belongsTo(\App\Models\School::class);
    }

    public function getIsDefaultAttribute()
    {
        $protected = ['super_admin', 'admin', 'teacher', 'student', 'parent', 'accountant', 'driver'];

        if (in_array($this->name, $protected)) {
            return true;
        }

        return false;
    }
}
