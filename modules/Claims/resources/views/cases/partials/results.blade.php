<div data-ajax-results>
    @php
        $link = fn (array $params) => route('claims.index', array_filter($params, fn ($v) => $v !== null && $v !== ''));
    @endphp

    <x-ui.status-tabs
        class="mb-4"
        :active="$status"
        :tabs="[
            'open' => ['label' => __('Open'), 'count' => $counts['open'], 'href' => $link(['status' => 'open', 'q' => $search]), 'ajax' => true],
            'closed' => ['label' => __('Closed'), 'count' => $counts['closed'], 'href' => $link(['status' => 'closed', 'q' => $search]), 'ajax' => true],
        ]"
    />

    @if ($stages !== [])
        <div class="-mx-1 mb-4 flex flex-wrap gap-2 px-1" role="group" aria-label="{{ __('Filter by stage') }}">
            <a href="{{ $link(['status' => $status, 'q' => $search]) }}" data-ajax-pill
                @class([
                    'inline-flex min-h-11 items-center gap-2 rounded-full px-4 text-sm font-semibold ring-1 ring-inset transition-colors',
                    'bg-brand-700 text-white ring-brand-700' => $stage === null,
                    'bg-white text-stone-700 ring-stone-300 hover:bg-stone-50' => $stage !== null,
                ])
                @if ($stage === null) aria-current="true" @endif>
                {{ __('All stages') }}
            </a>
            @foreach ($stages as $s)
                @php $count = (int) ($stageCounts[$s->value] ?? 0); @endphp
                <a href="{{ $link(['status' => $status, 'stage' => $s->value, 'q' => $search]) }}" data-ajax-pill
                    @class([
                        'inline-flex min-h-11 items-center gap-2 rounded-full px-4 text-sm font-semibold ring-1 ring-inset transition-colors',
                        'bg-brand-700 text-white ring-brand-700' => $stage === $s,
                        'bg-white text-stone-700 ring-stone-300 hover:bg-stone-50' => $stage !== $s,
                        'opacity-60' => $count === 0 && $stage !== $s,
                    ])
                    @if ($stage === $s) aria-current="true" @endif>
                    {{ $s->label() }}
                    <span @class(['rounded-full px-1.5 text-xs tabular', 'bg-white/20' => $stage === $s, 'bg-stone-100 text-stone-600' => $stage !== $s])>{{ $count }}</span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($cases->isEmpty())
        <x-ui.card>
            @if ($search !== '' || $stage)
                <x-ui.empty-state icon="search" :title="__('No claims match')" :description="__('Try a different search or clear the stage filter.')">
                    <x-ui.button :href="route('claims.index', ['status' => $status])" variant="secondary" data-ajax-pill>{{ __('Clear filters') }}</x-ui.button>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state
                    icon="scale"
                    :title="$status === 'closed' ? __('No closed claims') : __('No open claims')"
                    :description="__('When a school escalates a 90-day debt, the claim pack appears here.')"
                >
                    @if ($canOpen)
                        <x-ui.button :href="route('claims.create')" icon="plus">{{ __('Open a claim') }}</x-ui.button>
                    @endif
                </x-ui.empty-state>
            @endif
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ trans_choice(':count case|:count cases', $cases->total()) }}</span></x-slot:toolbar>
            <x-slot:head>
                <th scope="col">{{ __('Reference') }}</th>
                <th scope="col">{{ __('Debtor') }}</th>
                <th scope="col" class="hidden lg:table-cell">{{ __('School') }}</th>
                <th scope="col" class="text-right">{{ __('Amount') }}</th>
                <th scope="col">{{ __('Stage') }}</th>
                <th scope="col" class="hidden xl:table-cell">{{ __('Lawyer') }}</th>
                <th scope="col" class="hidden md:table-cell">{{ __('Next hearing') }}</th>
            </x-slot:head>
            @foreach ($cases as $case)
                @php $next = $case->nextHearing(); @endphp
                <tr>
                    <td class="whitespace-nowrap">
                        <a href="{{ route('claims.show', $case) }}" class="inline-flex min-h-11 items-center font-semibold tabular text-brand-700 hover:text-brand-800">{{ $case->reference }}</a>
                    </td>
                    <td class="max-w-[12rem]">
                        <span class="block truncate font-medium text-stone-900">{{ $case->debtor_name }}</span>
                        <span class="block truncate text-xs text-stone-500 lg:hidden">{{ $case->school?->name }}</span>
                    </td>
                    <td class="hidden max-w-[14rem] truncate lg:table-cell">{{ $case->school?->name }}</td>
                    <td class="whitespace-nowrap text-right font-semibold tabular text-stone-900">{{ $case->amount() }}</td>
                    <td><x-ui.badge :tone="$case->stage->tone()">{{ $case->stage->label() }}</x-ui.badge></td>
                    <td class="hidden xl:table-cell">{{ $case->lawyer?->name ?? __('Unassigned') }}</td>
                    <td class="hidden whitespace-nowrap tabular md:table-cell">{{ $next ? $next->scheduled_at->format('j M, g:ia') : '—' }}</td>
                </tr>
            @endforeach
            <x-slot:footer>{{ $cases->links() }}</x-slot:footer>
        </x-ui.table>
    @endif
</div>
