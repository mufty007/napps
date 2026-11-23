@php
    use App\Support\Money;
    $display = $invoice->displayStatus();
    $lines = $invoice->lines ?? [];
    $balance = $invoice->balanceKobo();
@endphp

<x-layouts.app :title="$invoice->student_name">
    <x-ui.page-header
        :eyebrow="$school?->name"
        :title="$invoice->student_name"
        :description="__('Invoice :ref · due :date', ['ref' => $invoice->reference, 'date' => $invoice->due_date->format('j M Y')])"
    >
        <x-slot:actions>
            <x-ui.badge :tone="$display->tone()" dot class="text-sm">{{ $display->label() }}</x-ui.badge>
            <x-ui.button :href="route('fees.parent.index')" variant="secondary" icon="arrow-left">{{ __('My fees') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mx-auto max-w-3xl space-y-6">
        @if ($processing)
            <x-ui.alert tone="info" :title="__('A payment is being confirmed')">
                {{ __('Your bank has not confirmed your last payment yet. This page updates once it does; you do not need to pay again.') }}
            </x-ui.alert>
        @endif

        @if ($invoice->isOpen() && $display === \Modules\Fees\Enums\InvoiceStatus::Overdue)
            <x-ui.alert tone="warning" :title="trans_choice(':count day overdue|:count days overdue', $invoice->daysOverdue())">
                {{ __('Please pay as soon as you can. If you have a problem, speak to the school bursar.') }}
            </x-ui.alert>
        @endif

        <x-ui.card :title="__('Charges')">
            @if ($lines !== [])
                <ul class="divide-y divide-stone-100 text-sm">
                    @foreach ($lines as $line)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-stone-700">{{ $line['name'] }}</span>
                            <span class="font-semibold tabular text-stone-900">{{ Money::format($line['amount_kobo']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <dl class="@if ($lines !== []) mt-3 border-t border-stone-200 pt-3 @endif space-y-1.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-stone-500">{{ __('Total') }}</dt><dd class="font-semibold tabular text-stone-900">{{ Money::format($invoice->total_kobo) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">{{ __('Paid so far') }}</dt><dd class="font-semibold tabular text-emerald-700">{{ Money::format($invoice->paid_kobo) }}</dd></div>
                <div class="flex justify-between gap-3 text-base"><dt class="font-medium text-stone-800">{{ __('Balance') }}</dt><dd class="font-semibold tabular text-stone-900">{{ Money::format($balance) }}</dd></div>
            </dl>
        </x-ui.card>

        @if ($invoice->isOpen() && $balance > 0)
            <x-ui.card :title="__('Pay online')" :description="__('Card, bank transfer or USSD through Paystack.')">
                <form method="POST" action="{{ route('fees.parent.pay', $invoice) }}" class="space-y-4" x-data="{ part: @js((bool) old('amount')) }">
                    @csrf
                    <div x-show="part" x-cloak>
                        <x-ui.input name="amount" inputmode="decimal" :label="__('Amount to pay now')" prefix="₦" :value="old('amount') ?? Money::toNaira($balance)"
                            :hint="__('Up to :balance. You can pay the rest later.', ['balance' => Money::format($balance)])" x-bind:disabled="! part" />
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button type="submit" size="lg" icon="credit-card" class="w-full sm:w-auto">
                            <span x-show="! part">{{ __('Pay :amount online', ['amount' => Money::format($balance)]) }}</span>
                            <span x-show="part" x-cloak>{{ __('Pay this amount') }}</span>
                        </x-ui.button>
                        <button type="button" class="inline-flex min-h-11 items-center px-2 text-sm font-semibold text-brand-700 hover:text-brand-800" @click="part = ! part"
                            x-text="part ? @js(__('Pay the full balance')) : @js(__('Pay part of it'))"></button>
                    </div>
                </form>
            </x-ui.card>
        @endif

        @if (! $hasAgreement)
            <x-ui.alert tone="info" :title="__('Sign the fee agreement')">
                {{ __('Confirm your agreement with :school and choose whether you want SMS reminders.', ['school' => $school?->name]) }}
                <div class="mt-3">
                    <x-ui.button :href="route('fees.agreements.create', ['school' => $invoice->school_id])" variant="secondary" size="sm" icon="file-signature">{{ __('Sign agreement') }}</x-ui.button>
                </div>
            </x-ui.alert>
        @endif

        <x-ui.card :title="__('Payments')" :padding="$invoice->payments->isEmpty()">
            @if ($invoice->payments->isEmpty())
                <x-ui.empty-state icon="wallet" :title="__('No payments yet')" class="py-6" />
            @else
                <ul class="divide-y divide-stone-100 text-sm">
                    @foreach ($invoice->payments as $payment)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <span>
                                <span class="block font-semibold text-stone-900">{{ $payment->method->label() }}</span>
                                <span class="block text-stone-500">{{ $payment->paid_at->format('j M Y, g:ia') }}@if ($payment->reference) · {{ $payment->reference }}@endif</span>
                            </span>
                            <span class="font-semibold tabular text-stone-900">{{ Money::format($payment->amount_kobo) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
