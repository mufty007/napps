<x-layouts.app :title="$charge->title">
    @php
        $rate = $totals->expected > 0 ? (int) round($totals->collected / $totals->expected * 100) : 0;
    @endphp

    <x-ui.page-header
        :eyebrow="$charge->placeName().' · '.$charge->kind->label()"
        :title="$charge->title"
        :description="__(':amount per school, due :date.', ['amount' => \App\Support\Money::format($charge->amount_kobo), 'date' => $charge->due_date->format('j M Y')])"
    >
        <x-slot:actions>
            @can('update', $charge)
                <x-ui.button :href="route('membership.charges.edit', $charge)" variant="secondary" icon="pencil">{{ __('Edit') }}</x-ui.button>
            @endcan
            @can('issue', $charge)
                <x-ui.button icon="send" x-on:click="$dispatch('open-modal', 'issue')">{{ __('Issue to schools') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($charge->isCancelled())
        <x-ui.alert tone="warning" :title="__('Withdrawn :date', ['date' => $charge->cancelled_at->format('j M Y')])" class="mb-6">
            {{ __('Unpaid bills were cancelled. Payments already made stay on record.') }}
        </x-ui.alert>
    @elseif (! $charge->isIssued())
        <x-ui.alert tone="info" :title="__('Draft')" class="mb-6">
            {{ trans_choice('Issuing bills :count member school and texts its proprietor. You cannot change the amount afterwards.|Issuing bills :count member schools and texts their proprietors. You cannot change the amount afterwards.', $schoolsToBill) }}
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat :label="__('Schools billed')" :value="(int) $totals->billed" icon="school" />
        <x-ui.stat :label="__('Paid')" :value="(int) $totals->paid" icon="badge-check" />
        <x-ui.stat :label="__('Collected')" :value="(int) round($totals->collected / 100)" format="currency" icon="wallet" />
        <x-ui.stat :label="__('Collection rate')" :value="$rate" format="percent" icon="chart-pie" />
    </div>

    @if ($charge->shares())
        <p class="mt-4 text-sm text-stone-600">
            {{ __('Split') }}:
            {{ collect($charge->shares())->map(fn ($percent, $level) => \App\Enums\ScopeLevel::from($level)->label().' '.$percent.'%')->implode(' · ') }}
        </p>
    @endif

    @if ($charge->isIssued())
        <x-ui.card class="mt-6" :title="__('Schools')" :padding="$dues->isEmpty()">
            <x-slot:actions>
                <div class="flex gap-1">
                    @foreach ([null => __('All'), 'unpaid' => __('Unpaid'), 'paid' => __('Paid')] as $value => $label)
                        <x-ui.button size="sm" :variant="($status?->value) === ($value ?: null) ? 'secondary' : 'ghost'" :href="route('membership.charges.show', [$charge, 'status' => $value ?: null])">{{ $label }}</x-ui.button>
                    @endforeach
                </div>
            </x-slot:actions>

            @if ($dues->isEmpty())
                <x-ui.empty-state icon="school" :title="__('No schools here')" :description="__('Bills appear here within a minute of issuing.')" class="py-4" />
            @else
                <x-ui.table flush>
                    <x-slot:head>
                        <th scope="col">{{ __('School') }}</th>
                        <th scope="col" class="hidden sm:table-cell">{{ __('Chapter') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </x-slot:head>
                    @foreach ($dues as $due)
                        <tr>
                            <td>
                                @can('org.view')
                                    <a href="{{ route('membership.schools.show', $due->school_id) }}" class="font-semibold text-stone-900 hover:text-brand-700">{{ $due->school?->name }}</a>
                                @else
                                    <span class="font-semibold text-stone-900">{{ $due->school?->name }}</span>
                                @endcan
                            </td>
                            <td class="hidden sm:table-cell">{{ $due->school?->chapter?->name }}</td>
                            <td>
                                <x-ui.badge :tone="$due->isOverdue() ? 'danger' : $due->status->tone()">{{ $due->isOverdue() ? __('Overdue') : $due->status->label() }}</x-ui.badge>
                                @if ($due->paid_at)<span class="ml-1 text-xs text-stone-500">{{ $due->paid_at->format('j M') }}</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>
        <div class="mt-6">{{ $dues->links() }}</div>
    @endif

    @can('cancel', $charge)
        <div class="mt-8 border-t border-stone-200 pt-6">
            <x-ui.button variant="ghost" class="text-red-700 hover:bg-red-50" icon="trash-2" x-on:click="$dispatch('open-modal', 'cancel')">
                {{ $charge->isIssued() ? __('Withdraw this charge') : __('Delete draft') }}
            </x-ui.button>
        </div>

        <x-ui.modal name="cancel" :title="$charge->isIssued() ? __('Withdraw :title?', ['title' => $charge->title]) : __('Delete this draft?')">
            <p class="text-sm text-stone-600">
                {{ $charge->isIssued()
                    ? __('Unpaid bills are cancelled and schools stop owing them. Payments already made stay on record and are not refunded automatically.')
                    : __('The draft is set aside. Nothing was billed.') }}
            </p>
            <form method="POST" action="{{ route('membership.charges.cancel', $charge) }}" class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                @csrf
                <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'cancel')">{{ __('Keep it') }}</x-ui.button>
                <x-ui.button type="submit" variant="danger">{{ $charge->isIssued() ? __('Withdraw') : __('Delete draft') }}</x-ui.button>
            </form>
        </x-ui.modal>
    @endcan

    @can('issue', $charge)
        <x-ui.modal name="issue" :title="__('Issue :title?', ['title' => $charge->title])">
            <p class="text-sm text-stone-600">
                {{ trans_choice(':count member school will be billed :amount, due :date, and its proprietor gets an SMS.|:count member schools will each be billed :amount, due :date, and their proprietors get an SMS.', $schoolsToBill, [
                    'amount' => \App\Support\Money::format($charge->amount_kobo),
                    'date' => $charge->due_date->format('j M Y'),
                ]) }}
            </p>
            <form method="POST" action="{{ route('membership.charges.issue', $charge) }}" class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                @csrf
                <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'issue')">{{ __('Not yet') }}</x-ui.button>
                <x-ui.button type="submit" icon="send">{{ __('Issue now') }}</x-ui.button>
            </form>
        </x-ui.modal>
    @endcan
</x-layouts.app>
