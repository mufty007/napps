<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * The gateway could not be reached or refused the request. Callers retry
 * later; the reconciliation sweep picks up anything left pending.
 */
class PaymentGatewayException extends RuntimeException {}
