<?php

namespace Modules\Fees\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Partial = 'partial';
    case Paid = 'paid';
    case Void = 'void';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Issued => __('Issued'),
            self::Partial => __('Part paid'),
            self::Paid => __('Paid'),
            self::Void => __('Void'),
            self::Overdue => __('Overdue'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Issued => 'info',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Void => 'neutral',
            self::Overdue => 'danger',
        };
    }

    /**
     * Statuses that still expect money.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Issued, self::Partial, self::Overdue];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }
}
