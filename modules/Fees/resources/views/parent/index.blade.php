@php use App\Support\Money; @endphp

<x-layouts.app :title="__('My fees')">
    <x-ui.page-header
        :eyebrow="__('Fees')"
        :title="__('My fees')"
        :description="__('What you owe each school, what you have paid, and your signed agreements.')"
    >
        <x-slot:actions>
            @if ($schools->isNotEmpty())
                <x-ui.button :href="route('fees.agreements.create')" variant="secondary" icon="file-signature">{{ __('Sign agreement') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($open->isEmpty() && $settled->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="receipt"
                :title="__('No invoices yet')"
                :description="__('When your child\'s school sends a fee invoice to your phone number, it appears here and you can pay it online.')"
            />
        </x-ui.card>
    @else
        <div class="space-y-6">
            @if ($owedKobo > 0)
                <x-ui.stat :label="__('Total to pay')" :value="intdiv($owedKobo, 100)" format="currency" icon="wallet"
                    :hint="trans_choice(':count open invoice|:count open invoices', $open->count())" />
            @else
                <x-ui.alert tone="success" :title="__('You are paid up')">{{ __('There is nothing to pay right now.') }}</x-ui.alert>
            @endif

            @if ($open->isNotEmpty())
                <x-ui.card :title="__('To pay')" :padding="false">
                    <ul class="divide-y divide-stone-100">
                        @foreach ($open as $invoice)
                            @php $display = $invoice->displayStatus(); @endphp
                            <li>
                                <a href="{{ route('fees.parent.show', $invoice) }}" class="flex min-h-16 flex-wrap items-center justify-between gap-3 px-5 py-4 transition-colors hover:bg-stone-50">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-stone-900">{{ $invoice->student_name }}</span>
                                        <span class="block truncate text-sm text-stone-500">
                                            {{ $schools[$invoice->school_id] ?? '' }} ·
                                            <span @class(['font-semibold text-red-700' => $display === \Modules\Fees\Enums\InvoiceStatus::Overdue])>
                                                {{ $display === \Modules\Fees\Enums\InvoiceStatus::Overdue ? __('overdue since :date', ['date' => $invoice->due_date->format('j M Y')]) : __('due :date', ['date' => $invoice->due_date->format('j M Y')]) }}
                                            </span>
                                        </span>
                                    </span>
                                    <span class="flex items-center gap-3">
                                        <x-ui.badge :tone="$display->tone()" dot>{{ $display->label() }}</x-ui.badge>
                                        <span class="text-base font-semibold tabular text-stone-900">{{ Money::format($invoice->balanceKobo()) }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            @if ($settled->isNotEmpty())
                <x-ui.card :title="__('Paid')" :padding="false">
                    <ul class="divide-y divide-stone-100">
                        @foreach ($settled as $invoice)
                            <li>
                                <a href="{{ route('fees.parent.show', $invoice) }}" class="flex min-h-14 items-center justify-between gap-3 px-5 py-3 transition-colors hover:bg-stone-50">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-stone-900">{{ $invoice->student_name }}</span>
                                        <span class="block truncate text-sm text-stone-500">{{ $schools[$invoice->school_id] ?? '' }} · {{ $invoice->reference }}</span>
                                    </span>
                                    <span class="flex items-center gap-3">
                                        <x-ui.badge tone="success" dot>{{ __('Paid') }}</x-ui.badge>
                                        <span class="text-sm font-semibold tabular text-stone-700">{{ Money::format($invoice->total_kobo) }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            @if ($agreements->isNotEmpty())
                <x-ui.card :title="__('Signed agreements')">
                    <ul class="divide-y divide-stone-100 text-sm">
                        @foreach ($agreements as $agreement)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-2.5">
                                <span>
                                    <span class="block font-semibold text-stone-900">{{ $agreement->student_name }}</span>
                                    <span class="block text-stone-500">{{ $schools[$agreement->school_id] ?? '' }} · {{ $agreement->signed_at->format('j M Y') }}</span>
                                </span>
                                @if ($agreement->pdf_path)
                                    <x-ui.button :href="route('fees.agreements.show', $agreement)" variant="ghost" size="sm" icon="download" target="_blank" rel="noopener">{{ __('Copy') }}</x-ui.button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>
    @endif
</x-layouts.app>
