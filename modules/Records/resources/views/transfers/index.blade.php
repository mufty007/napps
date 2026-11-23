@php
    $link = fn (string $b) => route('records.transfers.index', ['box' => $b]);
@endphp
<x-layouts.app :title="__('Transfers')">
    <x-ui.page-header
        :eyebrow="__('Records')"
        :title="__('Transfer clearance')"
        :description="__('Schools reply within :days working days when a pupil moves.', ['days' => $sla])"
    >
        <x-slot:actions>
            @can('create', \Modules\Records\Models\TransferRequest::class)
                <x-ui.button :href="route('records.transfers.create')" icon="plus">{{ __('Request clearance') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.status-tabs class="mb-4" :active="$box" :tabs="[
        'incoming' => ['label' => __('Needs our reply'), 'count' => $counts['incoming'], 'href' => $link('incoming')],
        'outgoing' => ['label' => __('We asked'), 'count' => $counts['outgoing'], 'href' => $link('outgoing')],
    ]" />

    @if ($requests->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="arrow-left-right"
                :title="$box === 'incoming' ? __('Nothing waiting for a reply') : __('You have not asked for any clearance')"
                :description="$box === 'incoming' ? __('When another school asks about one of your pupils, the request appears here.') : __('Moving a new pupil in? Ask their previous school to clear them.')"
            />
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ trans_choice(':count request|:count requests', $requests->total()) }}</span></x-slot:toolbar>
            <x-slot:head>
                <th scope="col">{{ __('Pupil') }}</th>
                <th scope="col" class="hidden md:table-cell">{{ $box === 'incoming' ? __('Asked by') : __('Holding school') }}</th>
                <th scope="col" class="hidden sm:table-cell">{{ __('Reply due') }}</th>
                <th scope="col">{{ __('Status') }}</th>
            </x-slot:head>
            @foreach ($requests as $transfer)
                <tr>
                    <td class="max-w-[14rem]">
                        <a href="{{ route('records.transfers.show', $transfer) }}" class="inline-flex min-h-11 items-center font-semibold text-brand-700 hover:text-brand-800">
                            <span class="truncate">{{ $transfer->student?->fullName() }}</span>
                        </a>
                    </td>
                    <td class="hidden max-w-[14rem] truncate md:table-cell">
                        {{ $box === 'incoming' ? ($transfer->toSchool?->name ?? __('Secretariat')) : $transfer->fromSchool?->name }}
                    </td>
                    <td class="hidden whitespace-nowrap tabular sm:table-cell">{{ $transfer->sla_due_at->format('D j M') }}</td>
                    <td>
                        @if ($transfer->isOverdue())
                            <x-ui.badge tone="danger" dot>{{ __('Overdue') }}</x-ui.badge>
                        @else
                            <x-ui.badge :tone="$transfer->status->tone()">{{ $transfer->status->label() }}</x-ui.badge>
                        @endif
                    </td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $requests->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</x-layouts.app>
