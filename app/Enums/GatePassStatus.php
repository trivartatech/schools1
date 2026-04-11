<?php

namespace App\Enums;

enum GatePassStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Exited   = 'exited';
    case Returned = 'returned';

    public function label(): string
    {
        return match($this) {
            self::Pending  => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Exited   => 'Exited',
            self::Returned => 'Returned',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending  => 'yellow',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Exited   => 'blue',
            self::Returned => 'gray',
        };
    }
}
