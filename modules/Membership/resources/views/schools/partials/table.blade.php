<div data-ajax-results>
    @if ($schools->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="school" :title="__('No schools match')" :description="__('Try a different search or clear the filters.')">
                <x-ui.button :href="route('membership.schools.index')" variant="secondary">{{ __('Clear filters') }}</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar>
                <span class="font-semibold text-stone-900">{{ trans_choice(':count school|:count schools', $schools->total()) }}</span>
                @if ($search || $status || request('state') || request('chapter'))
                    <a href="{{ route('membership.schools.index') }}" class="inline-flex min-h-11 items-center gap-1.5 font-semibold text-brand-700 hover:text-brand-800"><x-ui.icon name="x" size="size-4" /> {{ __('Clear filters') }}</a>
                @endif
            </x-slot:toolbar>
            <x-slot:head>
                <th scope="col">{{ __('School') }}</th>
                <th scope="col" class="hidden md:table-cell">{{ __('Chapter') }}</th>
                <th scope="col" class="text-right">{{ __('Outstanding') }}</th>
                <th scope="col">{{ __('Status') }}</th>
            </x-slot:head>
            @foreach ($schools as $school)
                <tr>
                    <td class="max-w-[14rem] sm:max-w-none">
                        <a href="{{ route('membership.schools.show', $school) }}" class="flex min-h-11 items-center gap-3 font-semibold text-stone-900 hover:text-brand-700">
                            <span class="hidden size-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700 sm:flex" aria-hidden="true"><x-ui.icon name="school" size="size-4" /></span>
                            <span class="min-w-0">
                                <span class="block truncate">{{ $school->name }}</span>
                                <span class="block text-xs font-normal text-stone-500 tabular">{{ $school->membership_number ?? __('No number yet') }}</span>
                            </span>
                        </a>
                    </td>
                    <td class="hidden md:table-cell">{{ $school->chapter?->name }}<span class="block text-xs text-stone-500">{{ $school->state?->name }}</span></td>
                    <td class="text-right font-semibold tabular text-stone-900">{{ $school->outstanding_kobo > 0 ? \App\Support\Money::format($school->outstanding_kobo) : '—' }}</td>
                    <td><x-ui.badge :tone="$school->membership_status->tone()" dot>{{ $school->membership_status->label() }}</x-ui.badge></td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $schools->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</div>
