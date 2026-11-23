<?php

namespace Modules\Fees\Support;

use Illuminate\Contracts\View\View;
use Modules\Fees\Services\FeeInvoiceService;

/** Feeds the arrears ageing card on the proprietor and bursar home. */
class ArrearsWidget
{
    public function __construct(private readonly FeeInvoiceService $invoices) {}

    public function compose(View $view): void
    {
        $ageing = $this->invoices->ageing();

        $view->with([
            'ageing' => $ageing,
            'ageingTotalKobo' => (int) collect($ageing)->sum('kobo'),
            'ageingMaxKobo' => max(1, (int) collect($ageing)->max('kobo')),
        ]);
    }
}
