<?php

namespace Modules\Fees\Policies;

use App\Models\User;
use App\Support\Scope\ActiveContext;
use Modules\Fees\Models\FeeInvoice;

/**
 * Staff see invoices through the school scope; parents see only invoices
 * addressed to their own account. Everything follows the *active* role.
 */
class FeeInvoicePolicy
{
    public function __construct(private readonly ActiveContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allows('fees.invoices.manage') || $this->context->allows('fees.arrears.view');
    }

    public function view(User $user, FeeInvoice $invoice): bool
    {
        return $this->viewAny($user)
            && FeeInvoice::query()->whereKey($invoice->getKey())->exists();
    }

    /** Raise invoices: needs a school to raise them for. */
    public function create(User $user): bool
    {
        return $this->context->allows('fees.invoices.manage') && $this->context->schoolId() !== null;
    }

    public function manage(User $user, FeeInvoice $invoice): bool
    {
        return $this->context->allows('fees.invoices.manage') && $this->view($user, $invoice);
    }

    public function recordPayment(User $user, FeeInvoice $invoice): bool
    {
        return $this->context->allows('fees.payments.record') && $this->view($user, $invoice);
    }

    /** Open a debt claim: whoever may open claims for the school, once the invoice is 90+ days late. */
    public function escalate(User $user, FeeInvoice $invoice): bool
    {
        return $this->context->allows('claims.open') && $this->view($user, $invoice);
    }

    /** A parent viewing and paying their own invoice. */
    public function viewOwn(User $user, FeeInvoice $invoice): bool
    {
        return $this->context->allows('fees.pay') && $invoice->guardian_user_id === $user->getKey();
    }
}
