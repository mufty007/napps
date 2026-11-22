<?php

namespace Modules\Membership\Http\Requests;

use App\Enums\ScopeLevel;
use App\Support\Money;
use App\Support\Scope\ActiveContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Membership\Enums\DuesKind;

/**
 * Dues or a levy set at the active role's level. The split only covers the
 * setter's level and the levels below it (a state cannot give national a share).
 */
class DuesChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('membership.dues.configure') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $year = (int) now()->year;

        return [
            'title' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::enum(DuesKind::class)],
            'year' => ['required', 'integer', 'between:'.($year - 1).','.($year + 2)],
            'amount' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                $kobo = Money::toKobo(is_scalar($value) ? (string) $value : null);
                if ($kobo === null || $kobo < 100) {
                    $fail(__('Enter an amount in naira, at least ₦1.'));
                }
            }],
            'due_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'share_national' => ['nullable', 'integer', 'between:0,100'],
            'share_state' => ['nullable', 'integer', 'between:0,100'],
            'share_chapter' => ['nullable', 'integer', 'between:0,100'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['share_national', 'share_state', 'share_chapter'])) {
                    return;
                }

                if (array_sum($this->shares()) !== 100) {
                    $validator->errors()->add('share_national', __('The split must add up to 100%.'));
                }
            },
        ];
    }

    public function level(): ScopeLevel
    {
        return app(ActiveContext::class)->level() ?? ScopeLevel::National;
    }

    /**
     * Shares limited to the setter's level and below.
     *
     * @return array{share_national: int, share_state: int, share_chapter: int}
     */
    public function shares(): array
    {
        return match ($this->level()) {
            ScopeLevel::Chapter => ['share_national' => 0, 'share_state' => 0, 'share_chapter' => 100],
            ScopeLevel::State => [
                'share_national' => 0,
                'share_state' => $this->integer('share_state', 100),
                'share_chapter' => $this->integer('share_chapter'),
            ],
            default => [
                'share_national' => $this->integer('share_national', 100),
                'share_state' => $this->integer('share_state'),
                'share_chapter' => $this->integer('share_chapter'),
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function chargeData(): array
    {
        return [
            'title' => trim((string) $this->validated('title')),
            'kind' => $this->validated('kind'),
            'year' => (int) $this->validated('year'),
            'amount_kobo' => Money::toKobo((string) $this->validated('amount')),
            'due_date' => $this->validated('due_date'),
            'description' => $this->validated('description'),
            ...$this->shares(),
        ];
    }
}
