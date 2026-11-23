<?php

namespace Modules\Claims\Enums;

enum ClaimStage: string
{
    case DemandSent = 'demand_sent';
    case ResponseWindow = 'response_window';
    case Mediation = 'mediation';
    case Filed = 'filed';
    case Hearing = 'hearing';
    case Judgment = 'judgment';
    case Enforcement = 'enforcement';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::DemandSent => __('Demand letter sent'),
            self::ResponseWindow => __('Response window'),
            self::Mediation => __('Chapter mediation'),
            self::Filed => __('Filed in court'),
            self::Hearing => __('Hearing'),
            self::Judgment => __('Judgment'),
            self::Enforcement => __('Enforcement'),
            self::Closed => __('Closed'),
        };
    }

    /** Badge tone from the UI kit. */
    public function tone(): string
    {
        return match ($this) {
            self::DemandSent => 'neutral',
            self::ResponseWindow => 'info',
            self::Mediation, self::Enforcement => 'warning',
            self::Filed, self::Hearing, self::Judgment => 'brand',
            self::Closed => 'success',
        };
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    /** Court stages need a court name and file number on the case. */
    public function isCourtStage(): bool
    {
        return in_array($this, [self::Filed, self::Hearing, self::Judgment, self::Enforcement], true);
    }

    /** @return list<self> */
    public static function pipeline(): array
    {
        return self::cases();
    }
}
