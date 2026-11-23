<?php

namespace Modules\Fees\Support;

use App\Models\School;
use App\Support\Scope\ActiveContext;
use Illuminate\Contracts\View\View;
use Modules\Fees\Models\FeeInvoice;

/** Feeds the "fees to pay" card on the parent home. */
class ParentFeesWidget
{
    public function __construct(private readonly ActiveContext $context) {}

    public function compose(View $view): void
    {
        $user = $this->context->user();

        $open = $user
            ? FeeInvoice::withoutGlobalScopes()->where('guardian_user_id', $user->getKey())->unpaid()->orderBy('due_date')->get()
            : collect();

        $view->with([
            'feeInvoices' => $open->take(5),
            'feeOpenCount' => $open->count(),
            'feeOwedKobo' => (int) $open->sum(fn (FeeInvoice $i) => $i->balanceKobo()),
            'feeOverdueCount' => $open->filter(fn (FeeInvoice $i) => $i->daysOverdue() > 0)->count(),
            'feeSchools' => School::withoutGlobalScopes()->whereIn('id', $open->pluck('school_id')->unique())->pluck('name', 'id'),
        ]);
    }
}
