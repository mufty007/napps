<?php

namespace Modules\Records\Http\Controllers\Concerns;

use App\Models\School;
use App\Support\PhoneNumber;
use App\Support\Scope\ActiveContext;
use Illuminate\Support\Collection;

/**
 * School-level roles keep records for the school they are acting in. Roles with
 * no single school (the secretariat) pick one on the form; School::query() is
 * scoped to the active role, so a foreign id simply is not found.
 */
trait ChoosesSchool
{
    /**
     * @return array<string, list<string>>
     */
    protected function schoolRules(ActiveContext $context): array
    {
        return $context->schoolId() === null ? ['school_id' => ['required', 'integer']] : [];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function chosenSchool(ActiveContext $context, array $validated): ?School
    {
        return School::query()->find($context->schoolId() ?? $validated['school_id'] ?? null);
    }

    /**
     * @return Collection<int, string>
     */
    protected function schoolOptions(ActiveContext $context): Collection
    {
        return $context->schoolId() === null
            ? School::query()->orderBy('name')->limit(500)->pluck('name', 'id')
            : collect();
    }

    protected function phoneRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (filled($value) && PhoneNumber::normalize((string) $value) === null) {
                $fail(__('Enter a valid Nigerian phone number.'));
            }
        };
    }
}
