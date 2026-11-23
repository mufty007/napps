<div data-ajax-results>
    @php
        $link = fn (array $params) => route('fees.invoices.index', array_filter($params, fn ($v) => $v !== null && $v !== '' && $v !== 'all'));
    @endphp

    <x-ui.status-tabs
        class="mb-4"
        :active="$status"
        :tabs="[
            'all' => ['label' => __('All'), 'count' => $counts['all'], 'href' => $link(['status' => 'all', 'q' => $search]), 'ajax' => true],
            'unpaid' => ['label' => __('Unpaid'), 'count' => $counts['unpaid'], 'href' => $link(['status' => 'unpaid', 'q' => $search]), 'ajax' => true],
            'overdue' => ['label' => __('Overdue'), 'count' => $counts['overdue'], 'href' => $link(['status' => 'overdue', 'q' => $search]), 'ajax' => true],
            'paid' => ['label' => __('Paid'), 'count' => $counts['paid'], 'href' => $link(['status' => 'paid', 'q' => $search]), 'ajax' => true],
        ]"
    />

    @if ($invoices->isEmpty())
        <x-ui.card>
            @if ($search !== '')
                <x-ui.empty-state icon="search" :title="__('No invoices match')" :description="__('Try a different name, phone number or reference.')">
                    <x-ui.button :href="route('fees.invoices.index', ['status' => $status])" variant="secondary" data-ajax-tab>{{ __('Clear search') }}</x-ui.button>
                </x-ui.empty-state>
            @else
                @php
                    $emptyTitle = match ($status) {
                        'unpaid' => __('Nothing unpaid'),
                        'overdue' => __('Nothing overdue'),
                        'paid' => __('No paid invoices yet'),
                        default => __('No invoices yet'),
                    };
                @endphp
                <x-ui.empty-state
                    icon="receipt"
                    :title="$emptyTitle"
                    :description="__('Raise invoices for a class from a fee structure and families are told by SMS.')"
                >
                    @if ($canCreate)
                        <x-ui.button :href="route('fees.invoices.create')" icon="plus">{{ __('Raise invoices') }}</x-ui.button>
                    @endif
                </x-ui.empty-state>
            @endif
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar>
                <span class="font-semibold text-stone-900">{{ trans_choice(':count invoice|:count invoices', $invoices->total()) }}</span>
                <span class="flex flex-wrap items-center gap-3">
                    @if ($status !== 'paid' && $owedKobo > 0)
                        <span>{{ __('Owed') }}: <span class="font-semibold tabular text-stone-900">{{ \App\Support\Money::format($owedKobo) }}</span></span>
                    @endif
                    @if ($canCreate && $counts['overdue'] > 0)
                        <form method="POST" action="{{ route('fees.invoices.remind-overdue') }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary" size="sm" icon="bell-ring">{{ __('Remind overdue') }}</x-ui.button>
                        </form>
                    @endif
                </span>
            </x-slot:toolbar>
            <x-slot:head>
                <th scope="col">{{ __('Child') }}</th>
                <th scope="col" class="hidden md:table-cell">{{ __('Parent') }}</th>
                <th scope="col" class="hidden whitespace-nowrap sm:table-cell">{{ __('Due') }}</th>
                <th scope="col" class="text-right">{{ __('Balance') }}</th>
                <th scope="col">{{ __('Status') }}</th>
            </x-slot:head>
            @foreach ($invoices as $invoice)
                @php $display = $invoice->displayStatus(); @endphp
                <tr>
                    <td class="max-w-[14rem]">
                        <a href="{{ route('fees.invoices.show', $invoice) }}" class="inline-flex min-h-11 max-w-full items-center truncate font-semibold text-brand-700 hover:text-brand-800">{{ $invoice->student_name }}</a>
                        <span class="block truncate text-xs text-stone-500">
                            {{ $invoice->reference }}@if ($showSchool) · {{ $invoice->school?->name }}@endif
                        </span>
                    </td>
                    <td class="hidden max-w-[12rem] truncate md:table-cell">
                        {{ $invoice->guardian_name ?? $invoice->guardian?->name ?? '—' }}
                        @if ($invoice->guardian_phone)<span class="block truncate text-xs text-stone-500">{{ \App\Support\PhoneNumber::format($invoice->guardian_phone) }}</span>@endif
                    </td>
                    <td class="hidden whitespace-nowrap tabular sm:table-cell">
                        {{ $invoice->due_date->format('j M Y') }}
                        @if ($invoice->daysOverdue() > 0)<span class="block text-xs font-semibold text-red-700">{{ trans_choice(':count day late|:count days late', $invoice->daysOverdue()) }}</span>@endif
                    </td>
                    <td class="whitespace-nowrap text-right font-semibold tabular text-stone-900">
                        {{ \App\Support\Money::format($invoice->balanceKobo()) }}
                        @if ($invoice->paid_kobo > 0 && $invoice->balanceKobo() > 0)<span class="block text-xs font-normal text-stone-500">{{ __('of :total', ['total' => \App\Support\Money::format($invoice->total_kobo)]) }}</span>@endif
                    </td>
                    <td><x-ui.badge :tone="$display->tone()" dot>{{ $display->label() }}</x-ui.badge></td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $invoices->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</div>
