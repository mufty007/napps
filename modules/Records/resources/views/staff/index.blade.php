<x-layouts.app :title="__('Staff records')">
    <x-ui.page-header
        :eyebrow="__('Records')"
        :title="__('Staff records')"
        :description="__('Everyone who works at your school, whether or not they sign in to NAPPS.')"
    >
        <x-slot:actions>
            @if ($canAdd)
                <x-ui.button :href="route('records.staff.create')" icon="plus">{{ __('Add staff member') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filter-bar method="GET" :action="route('records.staff.index')" class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <x-ui.input name="q" type="search" :label="__('Search')" :value="$search" :placeholder="__('Name, job title or phone')" autocomplete="off" />
        <x-ui.button type="submit" variant="secondary" icon="search">{{ __('Search') }}</x-ui.button>
    </x-ui.filter-bar>

    <x-ui.status-tabs class="mb-4" :active="$tab" :tabs="[
        'current' => ['label' => __('Current'), 'count' => $counts['current'], 'href' => route('records.staff.index', array_filter(['tab' => 'current', 'q' => $search]))],
        'former' => ['label' => __('Former'), 'count' => $counts['former'], 'href' => route('records.staff.index', array_filter(['tab' => 'former', 'q' => $search]))],
    ]" />

    @if ($members->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="users" :title="$search !== '' ? __('No staff match') : __('No staff on the register')" :description="__('Add teachers and other staff so your records are complete.')">
                @if ($canAdd && $search === '' && $tab === 'current')
                    <x-ui.button :href="route('records.staff.create')" icon="plus">{{ __('Add staff member') }}</x-ui.button>
                @endif
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ trans_choice(':count person|:count people', $members->total()) }}</span></x-slot:toolbar>
            <x-slot:head>
                <th scope="col">{{ __('Name') }}</th>
                <th scope="col">{{ __('Role') }}</th>
                <th scope="col" class="hidden md:table-cell">{{ __('Phone') }}</th>
                @if ($showSchool)
                    <th scope="col" class="hidden lg:table-cell">{{ __('School') }}</th>
                @endif
                <th scope="col" class="hidden sm:table-cell">{{ __('Started') }}</th>
                <th scope="col" class="hidden lg:table-cell">{{ __('Account') }}</th>
                <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
            </x-slot:head>
            @foreach ($members as $member)
                <tr>
                    <td class="max-w-[14rem] font-medium text-stone-900"><span class="block truncate">{{ $member->name }}</span></td>
                    <td class="max-w-[12rem]"><span class="block truncate">{{ $member->role_title }}</span></td>
                    <td class="hidden whitespace-nowrap tabular md:table-cell">{{ $member->phone ? \App\Support\PhoneNumber::format($member->phone) : '—' }}</td>
                    @if ($showSchool)
                        <td class="hidden max-w-[14rem] truncate lg:table-cell">{{ $member->school?->name }}</td>
                    @endif
                    <td class="hidden whitespace-nowrap tabular sm:table-cell">{{ $member->started_at?->format('j M Y') ?? '—' }}</td>
                    <td class="hidden lg:table-cell"><x-ui.badge :tone="$member->user_id ? 'success' : 'neutral'">{{ $member->user_id ? __('Signs in') : __('Register only') }}</x-ui.badge></td>
                    <td class="text-right">
                        @can('update', $member)
                            <a href="{{ route('records.staff.edit', $member) }}" class="inline-flex min-h-11 items-center px-2 text-sm font-semibold text-brand-700 hover:text-brand-800">{{ __('Edit') }}<span class="sr-only"> {{ $member->name }}</span></a>
                        @endcan
                    </td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $members->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</x-layouts.app>
