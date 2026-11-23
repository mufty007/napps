<?php

namespace Modules\Fees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGatewayException;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Fees\Enums\FeePaymentMethod;
use Modules\Fees\Models\FeeInvoice;
use Modules\Fees\Rules\NairaAmount;
use Modules\Fees\Services\FeeInvoiceService;

/**
 * FEE-2: taking money. Parents pay online (Paystack through the kernel
 * PaymentService); bursars record cash, transfers and teller deposits.
 */
class FeePaymentController extends Controller
{
    public function __construct(private readonly FeeInvoiceService $invoices) {}

    /**
     * Parent: open a checkout for their own invoice, in full or in part.
     */
    public function start(Request $request, FeeInvoice $parentInvoice): RedirectResponse
    {
        Gate::authorize('viewOwn', $parentInvoice);

        $validated = $request->validate(['amount' => ['nullable', new NairaAmount]]);

        try {
            $transaction = $this->invoices->startOnlinePayment(
                $parentInvoice,
                $request->user(),
                filled($validated['amount'] ?? null) ? Money::toKobo($validated['amount']) : null,
                route('fees.parent.show', $parentInvoice),
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (PaymentGatewayException $e) {
            report($e);

            return back()->with('error', __('We could not open the payment page. Please try again in a few minutes.'));
        }

        return redirect()->away((string) $transaction->authorization_url);
    }

    /**
     * Bursar: record cash, a bank transfer or a teller deposit.
     */
    public function store(Request $request, FeeInvoice $invoice): RedirectResponse
    {
        Gate::authorize('recordPayment', $invoice);

        $validated = $request->validate([
            'amount' => ['required', new NairaAmount],
            'method' => ['required', Rule::in(array_map(fn (FeePaymentMethod $m) => $m->value, FeePaymentMethod::offline()))],
            'reference' => ['nullable', 'string', 'max:64'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $payment = $this->invoices->recordPayment(
                invoice: $invoice,
                amountKobo: (int) Money::toKobo($validated['amount']),
                method: FeePaymentMethod::from($validated['method']),
                recordedBy: $request->user(),
                paidAt: filled($validated['paid_at'] ?? null) ? CarbonImmutable::parse($validated['paid_at'])->setTimeFrom(now()) : now(),
                reference: $validated['reference'] ?? null,
                meta: array_filter(['note' => $validated['note'] ?? null]),
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', __(':amount recorded.', ['amount' => Money::format($payment->amount_kobo)]));
    }
}
