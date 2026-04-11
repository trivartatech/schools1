<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubjectType extends Model
{
    use HasFactory;

    protected $fillable = ['school_id', 'label', 'description', 'sort_order'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }
}
