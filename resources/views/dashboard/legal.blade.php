<x-layouts.app :title="__('Home')">
    <x-ui.page-header :title="__('Assigned cases')" :description="__('Claim files that member schools have sent to you.')">
        <x-slot:actions>
            @if (Route::has('claims.index'))
                <x-ui.button :href="route('claims.index')" variant="secondary" icon-right="arrow-right">{{ __('All claims') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @php
        $summary = app(\Modules\Claims\Services\ClaimService::class)->legalSummary(auth()->user());
        $cases = $summary['open'];
        $hearings = $summary['hearings'];
    @endphp

    <div class="grid gap-4 sm:grid-cols-3" x-reveal>
        <x-ui.stat :label="__('Open cases')" :value="$summary['open_count']" icon="scale" :href="route('claims.index')" />
        <x-ui.stat :label="__('Hearings this week')" :value="$hearings->count()" icon="calendar" />
        <x-ui.stat :label="__('Amount in dispute')" :value="intdiv($summary['exposure_kobo'], 100)" format="currency" icon="hand-coins" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <x-ui.card :title="__('Open cases')">
            @if ($cases->isEmpty())
                <x-ui.empty-state icon="scale" :title="__('No cases assigned')" :description="__('When a school sends you a claim file, it appears here with its stage and documents.')" />
            @else
                <ul class="divide-y divide-stone-100">
                    @foreach ($cases as $case)
                        @php $next = $case->nextHearing(); @endphp
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <a href="{{ route('claims.show', $case) }}" class="inline-flex min-h-11 items-center font-semibold text-stone-900 hover:text-brand-800">{{ $case->debtor_name }}</a>
                                <p class="text-sm text-stone-500">{{ $case->reference }} · {{ $case->school?->name }} · {{ $case->amount() }}</p>
                                @if ($next)
                                    <p class="text-xs text-stone-500">{{ __('Next hearing :date', ['date' => $next->scheduled_at->format('j M, g:ia')]) }}</p>
                                @endif
                            </div>
                            <x-ui.badge :tone="$case->stage->tone()">{{ $case->stage->label() }}</x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Hearings this week')">
            @if ($hearings->isEmpty())
                <x-ui.empty-state icon="calendar" :title="__('No hearings this week')" :description="__('Hearings you schedule on a case show up here.')" />
            @else
                <ul class="divide-y divide-stone-100">
                    @foreach ($hearings as $hearing)
                        <li class="py-3 first:pt-0 last:pb-0">
                            <p @class(['text-sm font-semibold tabular', 'text-stone-900' => $hearing->scheduled_at->isFuture(), 'text-stone-400' => $hearing->scheduled_at->isPast()])>{{ $hearing->scheduled_at->format('D j M, g:ia') }}</p>
                            <a href="{{ route('claims.show', $hearing->claimCase) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-brand-700 hover:text-brand-800">{{ $hearing->claimCase->debtor_name }} · {{ $hearing->claimCase->reference }}</a>
                            @if ($hearing->venue)<p class="text-xs text-stone-500">{{ $hearing->venue }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <x-dashboard.widgets :widgets="$widgets" />
</x-layouts.app>
