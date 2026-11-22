<?php

namespace Modules\Membership\Enums;

enum DuesKind: string
{
    case Annual = 'annual';
    case Levy = 'levy';

    public function label(): string
    {
        return match ($this) {
            self::Annual => __('Annual dues'),
            self::Levy => __('Levy'),
        };
    }
}
