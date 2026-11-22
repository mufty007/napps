<?php

namespace App\Services\Payments;

use App\Enums\GatewayStatus;
use Carbon\CarbonImmutable;

/**
 * What a gateway reports for one transaction.
 */
final readonly class GatewayResult
{
    /**
     * @param  array<string, mixed>  $payload  Raw gateway data, kept for reconciliation.
     */
    public function __construct(
        public GatewayStatus $status,
        public ?int $amountKobo = null,
        public ?string $currency = null,
        public ?string $channel = null,
        public ?CarbonImmutable $paidAt = null,
        public ?string $message = null,
        public array $payload = [],
    ) {}
}
