<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'logo', 'address',
        'phone', 'email', 'website', 'status', 'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function schools()
    {
        return $this->hasMany(School::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
