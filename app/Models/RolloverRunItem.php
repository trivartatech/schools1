<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolloverRunItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'rollover_run_id', 'phase', 'item_type',
        'source_id', 'target_id', 'status', 'note', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function run() { return $this->belongsTo(RolloverRun::class, 'rollover_run_id'); }
}
