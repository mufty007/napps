<?php

namespace Modules\Fees\Enums;

enum FeePaymentMethod: string
{
    case Paystack = 'paystack';
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Teller = 'teller';

    public function label(): string
    {
        return match ($this) {
            self::Paystack => __('Online (Paystack)'),
            self::Cash => __('Cash'),
            self::Transfer => __('Bank transfer'),
            self::Teller => __('Bank teller'),
        };
    }

    /**
     * Methods a bursar can record by hand.
     *
     * @return list<self>
     */
    public static function offline(): array
    {
        return [self::Cash, self::Transfer, self::Teller];
    }
}
