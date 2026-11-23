{{-- Dashboard card for parents. Fed by Modules\Fees\Support\ParentFeesWidget. --}}
@if ($feeOpenCount > 0)
    <div class="mb-6" x-reveal>
        <x-ui.card :title="__('Fees to pay')">
            <x-slot:actions>
                <x-ui.button :href="route('fees.parent.index')" variant="ghost" size="sm" icon-right="arrow-right">{{ __('All fees') }}</x-ui.button>
            </x-slot:actions>
            <p class="text-sm text-stone-500">{{ __('Total owed') }}</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900 tabular">{{ \App\Support\Money::format($feeOwedKobo) }}</p>
            @if ($feeOverdueCount > 0)
                <p class="mt-1 text-sm font-semibold text-red-700">{{ trans_choice(':count invoice is overdue|:count invoices are overdue', $feeOverdueCount) }}</p>
            @endif

            <ul class="mt-4 divide-y divide-stone-100">
                @foreach ($feeInvoices as $invoice)
                    <li>
                        <a href="{{ route('fees.parent.show', $invoice) }}" class="flex min-h-14 items-center justify-between gap-3 py-2 hover:text-brand-800">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-stone-900">{{ $invoice->student_name }}</span>
                                <span class="block truncate text-xs text-stone-500">{{ $feeSchools[$invoice->school_id] ?? '' }} · {{ __('due :date', ['date' => $invoice->due_date->format('j M Y')]) }}</span>
                            </span>
                            <span class="text-sm font-semibold tabular text-stone-900">{{ \App\Support\Money::format($invoice->balanceKobo()) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    </div>
@endif
