<?php

namespace Modules\Records\Enums;

enum TransferStatus: string
{
    case Pending = 'pending';
    case Cleared = 'cleared';
    case Blocked = 'blocked';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting reply'),
            self::Cleared => __('Cleared'),
            self::Blocked => __('Blocked'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Cleared => 'success',
            self::Blocked => 'danger',
            self::Cancelled => 'neutral',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
