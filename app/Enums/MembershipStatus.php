<?php

namespace App\Enums;

/**
 * A school's standing with NAPPS (PRD: Membership & dues - compliance status).
 * "Pending" covers the application -> chapter verify -> state approve flow.
 */
enum MembershipStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Owing = 'owing';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Active => __('Active'),
            self::Owing => __('Owing'),
            self::Suspended => __('Suspended'),
        };
    }

    /**
     * Statuses of approved members (everything except a pending application).
     *
     * @return list<self>
     */
    public static function members(): array
    {
        return [self::Active, self::Owing, self::Suspended];
    }

    public function isMember(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * Badge tone used by <x-ui.badge>.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Active => 'success',
            self::Owing => 'warning',
            self::Suspended => 'danger',
        };
    }
}
