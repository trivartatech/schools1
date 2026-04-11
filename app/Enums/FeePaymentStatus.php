<?php

namespace App\Enums;

enum FeePaymentStatus: string
{
    case Paid    = 'paid';
    case Partial = 'partial';
    case Unpaid  = 'unpaid';
    case Waived  = 'waived';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::Paid     => 'Paid',
            self::Partial  => 'Partial',
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
            self::Unpaid   => 'red',
            self::Waived   => 'blue',
            self::Refunded => 'purple',
        };
    }
}
