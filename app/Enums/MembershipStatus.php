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
