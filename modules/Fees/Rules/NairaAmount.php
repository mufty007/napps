<?php

namespace Modules\Fees\Rules;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A positive amount in naira as people type it ("25,000", "25000.50"). */
class NairaAmount implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $kobo = Money::toKobo(is_scalar($value) ? $value : null);

        if ($kobo === null || $kobo <= 0) {
            $fail(__('Enter the amount in naira, for example 25000.'));
        }
    }
}
