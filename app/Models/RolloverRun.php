<?php

namespace App\Models;

use App\Enums\RolloverState;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RolloverRun extends Model
{
    use HasFactory, SoftDeletes, HasActivityLog;

    protected $fillable = [
        'school_id', 'source_year_id', 'target_year_id',
        'state', 'config', 'stats', 'error',
        'started_by', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'state'        => RolloverState::class,
        'config'       => 'array',
        'stats'        => 'array',
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
    ];

    public function school()      { return $this->belongsTo(School::class); }
    public function sourceYear()  { return $this->belongsTo(AcademicYear::class, 'source_year_id'); }
    public function targetYear()  { return $this->belongsTo(AcademicYear::class, 'target_year_id'); }
    public function startedBy()   { return $this->belongsTo(User::class, 'started_by'); }
    public function items()       { return $this->hasMany(RolloverRunItem::class); }

    public function transitionTo(RolloverState $next): void
    {
        $this->state = $next;
        if ($next === RolloverState::Finalized || $next === RolloverState::Failed || $next === RolloverState::Cancelled) {
            $this->finished_at = now();
        }
        $this->save();
    }

    public function bumpStat(string $key, int $by = 1): void
    {
        $stats = $this->stats ?? [];
        $stats[$key] = ($stats[$key] ?? 0) + $by;
        $this->stats = $stats;
        $this->save();
    }

    public function setStat(string $key, mixed $value): void
    {
        $stats = $this->stats ?? [];
        $stats[$key] = $value;
        $this->stats = $stats;
        $this->save();
    }

    public function logItem(string $phase, string $itemType, ?int $sourceId, ?int $targetId, string $status, ?string $note = null, array $meta = []): RolloverRunItem
    {
        return $this->items()->create([
            'phase'     => $phase,
            'item_type' => $itemType,
            'source_id' => $sourceId,
            'target_id' => $targetId,
            'status'    => $status,
            'note'      => $note,
            'meta'      => $meta ?: null,
        ]);
    }
}
