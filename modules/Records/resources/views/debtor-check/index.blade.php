@php
    $outstanding = $result && $result['status'] === \Modules\Records\Services\DebtorCheckService::OUTSTANDING;
@endphp
<x-layouts.app :title="__('Debtor check')">
    <x-ui.page-header
        :eyebrow="__('Records')"
        :title="__('Debtor check')"
        :description="__('Before enrolling a new pupil, ask whether the family owes fees at another member school.')"
    />

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="space-y-6">
            <x-ui.card :title="__('Check a guardian')">
                <form method="POST" action="{{ route('records.debtor-check.check') }}" class="space-y-5">
                    @csrf
                    <x-ui.input name="guardian_phone" type="tel" inputmode="tel" :label="__('Guardian\'s phone number')" :value="$result ? null : null" required autocomplete="off" />
                    <x-ui.checkbox name="consent_confirmed" value="1" :checked="false" :label="__('The guardian has agreed that I may run this check.')" />
                    @error('consent_confirmed')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                    <x-ui.button type="submit" icon="shield-check">{{ __('Run check') }}</x-ui.button>
                </form>
            </x-ui.card>

            @if ($result)
                <x-ui.card :title="__('Result for :phone', ['phone' => $result['phone']])">
                    <div role="status" @class([
                        'flex items-start gap-4 rounded-2xl p-5 ring-1 ring-inset',
                        'bg-amber-50 ring-amber-200' => $outstanding,
                        'bg-emerald-50 ring-emerald-200' => ! $outstanding,
                    ])>
                        <x-ui.icon :name="$outstanding ? 'triangle-alert' : 'circle-check'" size="size-8" @class(['text-amber-700' => $outstanding, 'text-emerald-700' => ! $outstanding]) />
                        <div>
                            <p class="text-lg font-semibold text-stone-900">{{ $outstanding ? __('Outstanding') : __('Clear') }}</p>
                            <p class="mt-1 text-sm text-stone-700">
                                @if ($outstanding)
                                    {{ __('Fees of :amount are outstanding at another member school.', ['amount' => $result['amount']]) }}
                                @else
                                    {{ __('No outstanding fees found among guardians who consented to this check.') }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <p class="mt-4 text-sm text-stone-500">{{ __('This check has been recorded in the audit log.') }}</p>
                </x-ui.card>
            @endif
        </div>

        <x-ui.side-panel :title="__('How this stays fair')">
            <ul class="space-y-3 text-sm text-stone-700">
                <li>{{ __('Only families who consented when enrolling at their school are included.') }}</li>
                <li>{{ __('You see “clear” or “outstanding” and a total. Never the school, the child or the invoices.') }}</li>
                <li>{{ __('Your own school\'s pupils are not part of the answer; see your arrears list for those.') }}</li>
                <li>{{ __('Every check is logged with who ran it, for NDPA access requests.') }}</li>
            </ul>
        </x-ui.side-panel>
    </div>
</x-layouts.app>
