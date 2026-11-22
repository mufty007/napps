<?php

namespace Modules\Membership\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Membership\Enums\PaymentMethod;

/**
 * An executive records dues paid at a meeting, by teller or by transfer.
 * Dues are paid in full: the amount is the sum of the dues ticked.
 */
class RecordDuesPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('membership.dues.record') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dues' => ['required', 'array', 'min:1'],
            'dues.*' => ['integer'],
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::offline()))],
            'reference' => ['nullable', 'string', 'max:64', 'required_if:method,'.PaymentMethod::BankTransfer->value],
            'paid_on' => ['required', 'date', 'before_or_equal:today', 'after:'.now()->subYears(2)->toDateString()],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dues.required' => __('Tick the dues this payment covers.'),
            'reference.required_if' => __('Enter the teller or transfer reference.'),
        ];
    }
}
