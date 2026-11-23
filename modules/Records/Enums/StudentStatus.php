<?php

namespace Modules\Records\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Transferred = 'transferred';
    case Exited = 'exited';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Transferred => __('Transferred'),
            self::Exited => __('Left school'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Transferred => 'info',
            self::Exited => 'neutral',
        };
    }
}
