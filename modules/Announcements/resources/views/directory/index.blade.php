<x-layouts.app :title="__('Member directory')">
    <x-ui.page-header
        :eyebrow="$fixedChapter?->name ?? __('Association')"
        :title="__('Member directory')"
        :description="__('Active member schools and their proprietors. Use it to find a neighbour school, a colleague or a chapter contact.')"
    />

    <x-ui.filter-bar :action="route('directory.index')" class="mb-6">
        <x-ui.input name="q" type="search" :label="__('Search')" :value="$term" :placeholder="__('School name or LGA')" class="w-full sm:w-72" />
        @if (! $fixedChapter)
            <x-ui.select name="chapter_id" :label="__('Chapter')" :options="$chapters->pluck('name', 'id')->all()" :placeholder="__('All chapters')" :value="$selected" class="w-full sm:w-60" />
        @endif
        <x-ui.button type="submit" variant="secondary" icon="search">{{ __('Search') }}</x-ui.button>
        @if ($term !== '' || ($selected && ! $fixedChapter))
            <x-ui.button :href="route('directory.index')" variant="ghost">{{ __('Clear') }}</x-ui.button>
        @endif
    </x-ui.filter-bar>

    @if ($schools->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="contact" :title="__('No schools found')" :description="__('Try a different name or chapter.')" />
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ trans_choice(':count school|:count schools', $schools->total()) }}</span></x-slot:toolbar>
            <x-slot:head>
                <th>{{ __('School') }}</th>
                <th>{{ __('Proprietor') }}</th>
                <th>{{ __('Chapter') }}</th>
                <th>{{ __('Levels') }}</th>
                <th>{{ __('Contact') }}</th>
            </x-slot:head>
            @foreach ($schools as $school)
                <tr>
                    <td>
                        <p class="font-semibold text-stone-900">{{ $school->name }}</p>
                        <p class="text-xs text-stone-500">{{ $school->lga }}@if ($school->membership_number) · {{ $school->membership_number }} @endif</p>
                    </td>
                    <td>{{ collect($proprietors[$school->id] ?? [])->implode(', ') ?: '—' }}</td>
                    <td>{{ $school->chapter?->name ?? '—' }}</td>
                    <td>{{ collect($school->levels ?? [])->map(fn ($level) => ucfirst($level))->implode(', ') ?: '—' }}</td>
                    <td class="whitespace-nowrap">
                        @if ($school->phone)
                            <a href="tel:{{ $school->phone }}" class="inline-flex min-h-11 items-center gap-1.5 font-semibold text-brand-700 hover:text-brand-800">
                                <x-ui.icon name="phone" size="size-4" /> {{ \App\Support\PhoneNumber::format($school->phone) }}
                            </a>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $schools->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</x-layouts.app>
