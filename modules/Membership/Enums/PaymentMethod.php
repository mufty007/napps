<?php

namespace Modules\Membership\Enums;

enum PaymentMethod: string
{
    case Online = 'online';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Pos = 'pos';

    public function label(): string
    {
        return match ($this) {
            self::Online => __('Online (Paystack)'),
            self::BankTransfer => __('Bank transfer or teller'),
            self::Cash => __('Cash'),
            self::Pos => __('POS'),
        };
    }

    /**
     * Methods an executive can record by hand.
     *
     * @return list<self>
     */
    public static function offline(): array
    {
        return [self::BankTransfer, self::Cash, self::Pos];
    }
}
