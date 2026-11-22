<?php

namespace App\Enums;

/**
 * Where in the State -> Chapter -> School chain a role assignment sits.
 *
 * "Personal" roles (parents, panel lawyers) are not anchored to an org unit;
 * they only see records explicitly linked to them (their children, cases
 * sent to them), so org-wide scopes deny everything for them by default.
 */
enum ScopeLevel: string
{
    case National = 'national';
    case State = 'state';
    case Chapter = 'chapter';
    case School = 'school';
    case Personal = 'personal';

    public function label(): string
    {
        return match ($this) {
            self::National => __('National'),
            self::State => __('State'),
            self::Chapter => __('Chapter'),
            self::School => __('School'),
            self::Personal => __('Personal'),
        };
    }

    public function requiresScopeId(): bool
    {
        return in_array($this, [self::State, self::Chapter, self::School], true);
    }
}
