<?php

namespace Modules\Membership\Enums;

enum ApplicationStatus: string
{
    /** Waiting for the chapter to verify. */
    case Submitted = 'submitted';

    /** Verified by the chapter, waiting for the state to approve. */
    case Verified = 'verified';

    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => __('Awaiting chapter verification'),
            self::Verified => __('Awaiting state approval'),
            self::Approved => __('Approved'),
            self::Rejected => __('Not approved'),
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Submitted => __('To verify'),
            self::Verified => __('To approve'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Verified => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Submitted, self::Verified], true);
    }
}
