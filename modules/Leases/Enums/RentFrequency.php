<?php

namespace Modules\Leases\Enums;

enum RentFrequency: string
{
    case Monthly = 'monthly';
    case Termly = 'termly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => __('Monthly'),
            self::Termly => __('Termly (3 a year)'),
            self::Yearly => __('Yearly'),
        };
    }

    public function perLabel(): string
    {
        return match ($this) {
            self::Monthly => __('per month'),
            self::Termly => __('per term'),
            self::Yearly => __('per year'),
        };
    }

    /** How many rent payments fall in a year. */
    public function paymentsPerYear(): int
    {
        return match ($this) {
            self::Monthly => 12,
            self::Termly => 3,
            self::Yearly => 1,
        };
    }
}
