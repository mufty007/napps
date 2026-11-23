<?php

namespace Modules\Leases\Enums;

enum DisputeStatus: string
{
    case Open = 'open';
    case ChapterHelp = 'chapter_help';
    case Legal = 'legal';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::ChapterHelp => __('Chapter helping'),
            self::Legal => __('With legal panel'),
            self::Resolved => __('Resolved'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::ChapterHelp => 'info',
            self::Legal => 'brand',
            self::Resolved => 'success',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Resolved;
    }
}
