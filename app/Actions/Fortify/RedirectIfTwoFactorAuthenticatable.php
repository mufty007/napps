<?php

namespace App\Actions\Fortify;

use App\Support\TwoFactor;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable as FortifyRedirect;

/**
 * Skip the TOTP challenge when 2FA enforcement is off (local/demo).
 */
class RedirectIfTwoFactorAuthenticatable extends FortifyRedirect
{
    public function handle($request, $next)
    {
        if (! TwoFactor::enforced()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
