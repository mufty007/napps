<?php

namespace Modules\Membership\Enums;

enum DueStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';

    /** The charge was withdrawn before this school paid. */
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::Paid => __('Paid'),
            self::Void => __('Withdrawn'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Paid => 'success',
            self::Void => 'neutral',
        };
    }
}
