<?php

namespace Modules\Membership\Providers;

use App\Support\Modules\ModuleServiceProvider;

/**
 * Member registry, onboarding (apply -> chapter verify -> state approve), digital certificate with QR, dues and split ledger, compliance status, public verify page. Stories: MEM-1..3.
 */
class MembershipServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Membership';
}
