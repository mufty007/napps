<x-layouts.app :title="__('NAPPS membership')">
    @php $total = (int) $unpaid->sum('amount_kobo'); @endphp

    <x-ui.page-header
        :eyebrow="$school->membership_number ?? __('NAPPS membership')"
        :title="__('NAPPS membership')"
        :description="__(':school · :chapter, :state', ['school' => $school->name, 'chapter' => $school->chapter?->name, 'state' => $school->state?->name])"
    >
        <x-slot:actions>
            <x-ui.badge :tone="$school->membership_status->tone()" dot class="text-sm">{{ $school->membership_status->label() }}</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="space-y-6">
        @if ($suspension)
            <x-ui.alert tone="danger" :title="__('Membership suspended')">
                {{ $suspension->reason }}.
                {{ $suspension->automatic ? __('Paying your overdue dues lifts this automatically.') : __('Contact your chapter executive to resolve it.') }}
            </x-ui.alert>
        @endif

        @if ($processing)
            <x-ui.alert tone="info" :title="__('A payment is being confirmed')">
                {{ __('Your bank has not confirmed your last payment yet. This page updates once it does; you do not need to pay again.') }}
            </x-ui.alert>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <x-ui.card :title="__('Dues to pay')" :padding="$unpaid->isEmpty()">
                @if ($unpaid->isEmpty())
                    <x-ui.empty-state icon="badge-check" :title="__('You are paid up')" :description="__('New dues and levies appear here, and we text you when they are issued.')" />
                @else
                    <ul class="divide-y divide-stone-100">
                        @foreach ($unpaid as $due)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-stone-900">{{ $due->charge->title }}</span>
                                    <span class="block text-sm text-stone-500">
                                        {{ $due->charge->placeName() }} ·
                                        <span @class(['font-semibold text-red-700' => $due->isOverdue()])>
                                            {{ $due->isOverdue() ? __('overdue since :date', ['date' => $due->due_date->format('j M Y')]) : __('due :date', ['date' => $due->due_date->format('j M Y')]) }}
                                        </span>
                                    </span>
                                    @if ($due->charge->description)
                                        <span class="mt-1 block text-sm text-stone-500">{{ $due->charge->description }}</span>
                                    @endif
                                </span>
                                <span class="text-base font-semibold text-stone-900 tabular">{{ \App\Support\Money::format($due->amount_kobo) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="flex flex-wrap items-center justify-between gap-4 border-t border-stone-100 bg-stone-50/60 px-5 py-4">
                        <p class="text-sm text-stone-600">{{ __('Total') }} <span class="ml-1 text-lg font-semibold text-stone-900 tabular">{{ \App\Support\Money::format($total) }}</span></p>
                        <form method="POST" action="{{ route('membership.dues.pay') }}" class="w-full sm:w-auto">
                            @csrf
                            <x-ui.button type="submit" size="lg" class="w-full" icon="credit-card">{{ __('Pay :amount online', ['amount' => \App\Support\Money::format($total)]) }}</x-ui.button>
                        </form>
                    </div>
                @endif
            </x-ui.card>

            <div class="space-y-6">
                <x-ui.card :title="__('Certificate')">
                    @if ($certificateUrl)
                        <p class="text-sm text-stone-600">{{ __('Print it for your office. Its QR code opens your school\'s live status, so parents can check it.') }}</p>
                        <x-ui.button :href="$certificateUrl" class="mt-4 w-full" variant="secondary" icon="award" target="_blank" rel="noopener">{{ __('Download certificate') }}</x-ui.button>
                        <a href="{{ route('verify.show', $school->membership_number) }}" class="mt-2 flex min-h-11 items-center justify-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800" target="_blank" rel="noopener">
                            <x-ui.icon name="external-link" size="size-4" /> {{ __('See what parents see') }}
                        </a>
                    @else
                        <p class="text-sm text-stone-600">{{ __('Issued when your membership is approved.') }}</p>
                    @endif
                </x-ui.card>

                <x-ui.card :title="__('How to pay')">
                    <p class="text-sm text-stone-600">{{ __('Pay by card, bank transfer or USSD through Paystack. Your receipt arrives by SMS and your status updates within a minute.') }}</p>
                    <p class="mt-3 text-sm text-stone-600">{{ __('Paid your chapter in cash or by teller? Ask them to record it so it shows here.') }}</p>
                </x-ui.card>
            </div>
        </div>

        <x-ui.card :title="__('Receipts')" :padding="$payments->isEmpty()">
            @if ($payments->isEmpty())
                <x-ui.empty-state icon="receipt" :title="__('No payments yet')" class="py-4" />
            @else
                <ul class="divide-y divide-stone-100">
                    @foreach ($payments as $payment)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <span class="min-w-0 text-sm">
                                <span class="block font-semibold text-stone-900 tabular">{{ \App\Support\Money::format($payment->amount_kobo) }}</span>
                                <span class="block text-stone-500">{{ $payment->paid_at->format('j M Y') }} · {{ $payment->method->label() }} · {{ $payment->receipt_number }}</span>
                            </span>
                            <x-ui.button :href="$documents->receiptUrl($payment, 1)" variant="secondary" size="sm" icon="download" target="_blank" rel="noopener">{{ __('Receipt') }}</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <p class="text-center text-sm text-stone-500">
            {{ __('Own another school?') }}
            <a href="{{ route('membership.apply') }}" class="font-semibold text-brand-700 hover:text-brand-800">{{ __('Apply for it too') }}</a>
        </p>
    </div>
</x-layouts.app>
