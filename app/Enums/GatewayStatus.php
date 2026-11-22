<?php

namespace App\Enums;

/**
 * State of an online payment attempt at the gateway.
 */
enum GatewayStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Abandoned = 'abandoned';

    /**
     * Success and failure never change again; pending and abandoned
     * attempts can still complete if the payer goes back to the checkout.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Success, self::Failed], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting payment'),
            self::Success => __('Paid'),
            self::Failed => __('Failed'),
            self::Abandoned => __('Not completed'),
        };
    }
}
