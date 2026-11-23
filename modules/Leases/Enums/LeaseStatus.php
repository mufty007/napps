<?php

namespace Modules\Leases\Enums;

enum LeaseStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Terminated = 'terminated';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Expired => __('Expired'),
            self::Terminated => __('Terminated'),
            self::Disputed => __('In dispute'),
        };
    }

    /** Badge tone from the UI kit. */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Expired => 'neutral',
            self::Terminated => 'neutral',
            self::Disputed => 'danger',
        };
    }

    /**
     * Statuses a proprietor may set by hand ("disputed" follows the dispute log).
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Active, self::Expired, self::Terminated];
    }
}
