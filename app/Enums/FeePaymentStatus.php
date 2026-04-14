<?php

namespace App\Enums;

enum FeePaymentStatus: string
{
    case Paid     = 'paid';
    case Partial  = 'partial';
    case Due      = 'due';      // matches DB enum value
    case Unpaid   = 'unpaid';
    case Waived   = 'waived';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::Paid     => 'Paid',
            self::Partial  => 'Partial',
            self::Due      => 'Due',
            self::Unpaid   => 'Unpaid',
            self::Waived   => 'Waived',
            self::Refunded => 'Refunded',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Paid     => 'green',
            self::Partial  => 'yellow',
            self::Due      => 'red',
            self::Unpaid   => 'red',
            self::Waived   => 'blue',
            self::Refunded => 'purple',
        };
    }
}
