<?php

namespace App\Enums;

enum PaymentMode: string
{
    case Cash         = 'cash';
    case Online       = 'online';
    case BankTransfer = 'bank_transfer';
    case Cheque       = 'cheque';
    case DD           = 'dd';
    case Neft         = 'neft';
    case Rtgs         = 'rtgs';
    case Upi          = 'upi';

    public function label(): string
    {
        return match($this) {
            self::Cash         => 'Cash',
            self::Online       => 'Online',
            self::BankTransfer => 'Bank Transfer',
            self::Cheque       => 'Cheque',
            self::DD           => 'Demand Draft',
            self::Neft         => 'NEFT',
            self::Rtgs         => 'RTGS',
            self::Upi          => 'UPI',
        };
    }
}
