<?php

namespace App\Enums;

enum StaffStatus: string
{
    case Active    = 'active';
    case Inactive  = 'inactive';
    case OnLeave   = 'on_leave';
    case Resigned  = 'resigned';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match($this) {
            self::Active     => 'Active',
            self::Inactive   => 'Inactive',
            self::OnLeave    => 'On Leave',
            self::Resigned   => 'Resigned',
            self::Terminated => 'Terminated',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Active     => 'green',
            self::Inactive   => 'gray',
            self::OnLeave    => 'yellow',
            self::Resigned   => 'orange',
            self::Terminated => 'red',
        };
    }
}
