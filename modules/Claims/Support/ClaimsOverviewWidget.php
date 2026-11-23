<?php

namespace Modules\Claims\Support;

use App\Support\Scope\ActiveContext;
use Illuminate\Contracts\View\View;
use Modules\Claims\Services\ClaimService;

/** Feeds the claims card on executive and secretariat dashboards. */
class ClaimsOverviewWidget
{
    public function __construct(
        private readonly ClaimService $claims,
        private readonly ActiveContext $context,
    ) {}

    public function compose(View $view): void
    {
        $view->with('overview', $this->claims->overview($this->context));
    }
}
