<?php

namespace App\Support;

/**
 * Production always requires 2FA for roles that need it.
 * Outside production, TWO_FACTOR_ENFORCE=false turns the gate off for every role
 * so demo accounts can be clicked through without an authenticator.
 */
final class TwoFactor
{
    public static function enforced(): bool
    {
        return app()->isProduction()
            ? true
            : (bool) config('napps.two_factor.enforce');
    }
}
