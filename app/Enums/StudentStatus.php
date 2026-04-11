<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active      = 'active';
    case Inactive    = 'inactive';
    case Transferred = 'transferred';
    case Graduated   = 'graduated';
    case Suspended   = 'suspended';

    public function label(): string
    {
        return match($this) {
            self::Active      => 'Active',
            self::Inactive    => 'Inactive',
            self::Transferred => 'Transferred',
            self::Graduated   => 'Graduated',
            self::Suspended   => 'Suspended',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Active      => 'green',
            self::Inactive    => 'gray',
            self::Transferred => 'blue',
            self::Graduated   => 'purple',
            self::Suspended   => 'red',
        };
    }
}
