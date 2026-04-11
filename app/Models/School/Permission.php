<?php

namespace App\Models\School;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $guarded = [];

    protected $primaryKey = 'id';

    protected $table = 'permissions';
}
