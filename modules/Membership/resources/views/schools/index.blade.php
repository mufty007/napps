<x-layouts.app :title="__('Schools')">
    <x-ui.page-header :eyebrow="__('Membership')" :title="__('Member schools')" :description="__('Every member school in your area and its dues standing.')" />

    <form method="GET" action="{{ route('membership.schools.index') }}" class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))_auto] lg:items-end">
        <x-ui.input name="q" type="search" :label="__('Search')" :value="$search" :placeholder="__('Name, membership or CAC number')" />
        <x-ui.select name="status" :label="__('Status')" :value="$status?->value" :placeholder="__('All members')"
            :options="collect(\App\Enums\MembershipStatus::members())->mapWithKeys(fn ($s) => [$s->value => $s->label()])" />
        @if ($states->isNotEmpty())
            <x-ui.select name="state" :label="__('State')" :value="request('state')" :placeholder="__('All states')" :options="$states" />
        @endif
        @if ($chapters->isNotEmpty())
            <x-ui.select name="chapter" :label="__('Chapter')" :value="request('chapter')" :placeholder="__('All chapters')" :options="$chapters" />
        @endif
        <x-ui.button type="submit" variant="secondary" icon="search">{{ __('Filter') }}</x-ui.button>
    </form>

    <x-ui.card :padding="$schools->isEmpty()">
        @if ($schools->isEmpty())
            <x-ui.empty-state icon="school" :title="__('No schools match')" :description="__('Try a different search or clear the filters.')">
                <x-ui.button :href="route('membership.schools.index')" variant="secondary">{{ __('Clear filters') }}</x-ui.button>
            </x-ui.empty-state>
        @else
            <x-ui.table>
                <x-slot:head>
                    <th scope="col">{{ __('School') }}</th>
                    <th scope="col" class="hidden md:table-cell">{{ __('Chapter') }}</th>
                    <th scope="col" class="text-right">{{ __('Outstanding') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                </x-slot:head>
                @foreach ($schools as $school)
                    <tr class="hover:bg-stone-50">
                        <td class="max-w-[14rem] sm:max-w-none">
                            <a href="{{ route('membership.schools.show', $school) }}" class="block min-h-11 py-1 font-semibold text-stone-900 hover:text-brand-700">
                                <span class="block truncate">{{ $school->name }}</span>
                                <span class="block text-xs font-normal text-stone-500">{{ $school->membership_number ?? __('No number yet') }}</span>
                            </a>
                        </td>
                        <td class="hidden md:table-cell">{{ $school->chapter?->name }}<span class="block text-xs text-stone-500">{{ $school->state?->name }}</span></td>
                        <td class="text-right tabular">{{ $school->outstanding_kobo > 0 ? \App\Support\Money::format($school->outstanding_kobo) : '—' }}</td>
                        <td><x-ui.badge :tone="$school->membership_status->tone()" dot>{{ $school->membership_status->label() }}</x-ui.badge></td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <div class="mt-6">{{ $schools->links() }}</div>
</x-layouts.app>
