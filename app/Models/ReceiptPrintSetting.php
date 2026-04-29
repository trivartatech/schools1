<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptPrintSetting extends Model
{
    public const PAPER_SIZES = ['A4', 'A5', 'A6'];

    public const MAX_COPIES = 4;

    public const COPY_LABELS = ['Original', 'Duplicate', 'Office Copy', 'Triplicate'];

    protected $fillable = [
        'school_id',
        'paper_size',
        'copies',
    ];

    protected $casts = [
        'copies' => 'integer',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public static function forSchool(int $schoolId): self
    {
        return self::firstOrCreate(
            ['school_id' => $schoolId],
            ['paper_size' => 'A4', 'copies' => 1],
        );
    }

    public function copyLabels(): array
    {
        $count = max(1, min($this->copies ?? 1, self::MAX_COPIES));
        return array_slice(self::COPY_LABELS, 0, $count);
    }
}
