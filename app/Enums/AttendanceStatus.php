<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present  = 'present';
    case Absent   = 'absent';
    case Late     = 'late';
    case HalfDay  = 'half_day';
    case Holiday  = 'holiday';
    case Leave    = 'leave';

    public function label(): string
    {
        return match($this) {
            self::Present  => 'Present',
            self::Absent   => 'Absent',
            self::Late     => 'Late',
            self::HalfDay  => 'Half Day',
            self::Holiday  => 'Holiday',
            self::Leave    => 'On Leave',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Present  => 'green',
            self::Absent   => 'red',
            self::Late     => 'yellow',
            self::HalfDay  => 'orange',
            self::Holiday  => 'blue',
            self::Leave    => 'purple',
        };
    }

    public function countsAsPresent(): bool
    {
        return in_array($this, [self::Present, self::Late, self::HalfDay]);
    }
}
