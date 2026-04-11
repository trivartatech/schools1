<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'custom_field_id',
        'model_type',
        'model_id',
        'value',
    ];

    /**
     * The custom field definition.
     */
    public function customField()
    {
        return $this->belongsTo(CustomField::class);
    }

    /**
     * Get the owning model (e.g., Student, Staff).
     */
    public function model()
    {
        return $this->morphTo();
    }
}
