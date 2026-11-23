@php
    $link = fn (array $params) => route('leases.index', array_filter($params, fn ($v) => $v !== null && $v !== '' && $v !== 'all'));
@endphp

<x-layouts.app :title="__('Leases')">
    <x-ui.page-header
        :eyebrow="__('Premises')"
        :title="__('Lease register')"
        :description="__('Every lease in one place: rent paid, renewal dates, signed documents and any dispute with the landlord.')"
    >
        <x-slot:actions>
            @if ($canCreate)
                <x-ui.button :href="route('leases.create')" icon="plus">{{ __('Add a lease') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.stat :label="__('Leases')" :value="$stats['total']" icon="building-2" :href="$link([])" />
        <x-ui.stat :label="__('Ending in 90 days')" :value="$stats['expiring']" icon="calendar-clock" :href="$link(['filter' => 'expiring'])" />
        <x-ui.stat :label="__('In dispute')" :value="$stats['in_dispute']" icon="scale" :href="$link(['filter' => 'disputed'])" />
        <x-ui.stat :label="__('Expired')" :value="$stats['expired']" icon="calendar-x" :href="$link(['filter' => 'expired'])" />
    </div>

    <x-ui.status-tabs
        class="mb-4"
        :active="$filter"
        :tabs="[
            'all' => ['label' => __('All'), 'href' => $link(['q' => $search])],
            'expiring' => ['label' => __('Ending soon'), 'href' => $link(['filter' => 'expiring', 'q' => $search])],
            'disputed' => ['label' => __('In dispute'), 'href' => $link(['filter' => 'disputed', 'q' => $search])],
            'expired' => ['label' => __('Expired'), 'href' => $link(['filter' => 'expired', 'q' => $search])],
        ]"
    />

    <x-ui.filter-bar method="GET" :action="route('leases.index')" class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
        @if ($filter !== 'all')<input type="hidden" name="filter" value="{{ $filter }}">@endif
        <x-ui.input name="q" type="search" :label="__('Search')" :value="$search" :placeholder="__('Landlord, address or school')" autocomplete="off" />
        <x-ui.button type="submit" variant="secondary" icon="search">{{ __('Search') }}</x-ui.button>
    </x-ui.filter-bar>

    @if ($leases->isEmpty())
        <x-ui.card>
            @if ($search !== '' || $filter !== 'all')
                <x-ui.empty-state icon="search" :title="__('No leases match')" :description="__('Try a different search or clear the filter.')">
                    <x-ui.button :href="route('leases.index')" variant="secondary">{{ __('Clear filters') }}</x-ui.button>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state icon="building-2" :title="__('No leases yet')" :description="__('Add your school premises lease so we can remind you 90, 60 and 30 days before it ends.')">
                    @if ($canCreate)
                        <x-ui.button :href="route('leases.create')" icon="plus">{{ __('Add a lease') }}</x-ui.button>
                    @endif
                </x-ui.empty-state>
            @endif
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ trans_choice(':count lease|:count leases', $leases->total()) }}</span></x-slot:toolbar>
            <x-slot:head>
                <th scope="col">{{ __('Property') }}</th>
                <th scope="col" class="hidden md:table-cell">{{ __('School') }}</th>
                <th scope="col" class="hidden lg:table-cell">{{ __('Landlord') }}</th>
                <th scope="col" class="hidden sm:table-cell text-right">{{ __('Rent') }}</th>
                <th scope="col">{{ __('Ends') }}</th>
                <th scope="col">{{ __('Status') }}</th>
            </x-slot:head>
            @foreach ($leases as $lease)
                @php
                    $days = $lease->daysToExpiry();
                    $tone = $days < 0 ? 'neutral' : ($days <= 30 ? 'danger' : ($days <= 60 ? 'warning' : ($days <= 90 ? 'info' : 'neutral')));
                @endphp
                <tr>
                    <td class="max-w-[14rem]">
                        <a href="{{ route('leases.show', $lease) }}" class="inline-flex min-h-11 max-w-full items-center truncate font-semibold text-brand-700 hover:text-brand-800">{{ \Illuminate\Support\Str::limit($lease->property_address, 50) }}</a>
                        <span class="block truncate text-xs text-stone-500 md:hidden">{{ $lease->school?->name }}</span>
                    </td>
                    <td class="hidden max-w-[14rem] truncate md:table-cell">{{ $lease->school?->name }}</td>
                    <td class="hidden max-w-[12rem] truncate lg:table-cell">{{ $lease->landlord_name }}</td>
                    <td class="hidden whitespace-nowrap text-right tabular sm:table-cell">
                        <span class="font-semibold text-stone-900">{{ $lease->rent() }}</span>
                        <span class="block text-xs text-stone-500">{{ $lease->rent_frequency->perLabel() }}</span>
                    </td>
                    <td class="whitespace-nowrap tabular">
                        {{ $lease->end_date->format('j M Y') }}
                        <x-ui.badge :tone="$tone" class="ml-1">{{ $days < 0 ? __('Ended') : trans_choice(':count day|:count days', $days) }}</x-ui.badge>
                    </td>
                    <td><x-ui.badge :tone="$lease->status->tone()" dot>{{ $lease->status->label() }}</x-ui.badge></td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $leases->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</x-layouts.app>
