<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Designation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'parent_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope a query to only include records for the current school.
     */
    public function scopeTenant($query)
    {
        if (app()->has('current_school_id')) {
            return $query->where('school_id', app('current_school_id'));
        }
        return $query;
    }

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

    public function parent()
    {
        return $this->belongsTo(Designation::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Designation::class, 'parent_id');
    }
}
