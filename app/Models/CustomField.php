<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'entity_type',
        'name',
        'label',
        'type',
        'options',
        'is_required',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'options'     => 'array',
        'is_required' => 'boolean',
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    /**
     * Scope to order fields logically.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Automatically boot and handle setting the sorting order if not specified.
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->sort_order) {
                $maxSort = static::where('school_id', $model->school_id)
                                ->where('entity_type', $model->entity_type)
                                ->max('sort_order');
                $model->sort_order = $maxSort ? $maxSort + 1 : 1;
            }
        });
    }

    /**
     * Relationship to the school.
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
