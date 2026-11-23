<?php

namespace Modules\Fees\Enums;

/**
 * When a reminder goes out relative to the due date: a few days before, on
 * the day, and 7, 14 and 30 days after.
 */
enum ReminderKind: string
{
    case Before = 'before';
    case On = 'on';
    case Week = '7';
    case Fortnight = '14';
    case Month = '30';

    public function label(): string
    {
        return match ($this) {
            self::Before => __('Due soon'),
            self::On => __('Due today'),
            self::Week => __('7 days overdue'),
            self::Fortnight => __('14 days overdue'),
            self::Month => __('30 days overdue'),
        };
    }

    /**
     * Days from the due date (negative = before) at which this reminder is
     * first due.
     */
    public function offsetDays(): int
    {
        return match ($this) {
            self::Before => -3,
            self::On => 0,
            self::Week => 7,
            self::Fortnight => 14,
            self::Month => 30,
        };
    }

    /**
     * Whether a day offset falls in this reminder's window. Each window is
     * two days wide so one missed nightly run does not lose the reminder.
     */
    public function covers(int $offsetDays): bool
    {
        return $offsetDays >= $this->offsetDays() && $offsetDays <= $this->offsetDays() + 1;
    }

    /**
     * The kind that best describes a manual reminder sent at this offset.
     */
    public static function forOffset(int $offsetDays): self
    {
        return match (true) {
            $offsetDays < 0 => self::Before,
            $offsetDays < 7 => self::On,
            $offsetDays < 14 => self::Week,
            $offsetDays < 30 => self::Fortnight,
            default => self::Month,
        };
    }
}
