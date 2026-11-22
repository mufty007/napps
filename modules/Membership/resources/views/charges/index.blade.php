<x-layouts.app :title="__('Dues & levies')">
    <x-ui.page-header
        :eyebrow="__('Membership')"
        :title="__('Dues & levies')"
        :description="__('What schools pay national, their state and their chapter. Figures cover schools in your area.')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('membership.charges.create')" icon="plus">{{ __('New charge') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <nav class="-mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="{{ __('Year') }}">
        <div class="flex gap-2">
            @foreach ($years as $option)
                <a href="{{ route('membership.charges.index', ['year' => $option]) }}"
                    @class([
                        'flex min-h-11 shrink-0 items-center rounded-xl px-4 text-sm font-semibold ring-1 ring-inset tabular',
                        'bg-brand-700 text-white ring-brand-700' => $option === $year,
                        'bg-white text-stone-700 ring-stone-200 hover:bg-stone-50' => $option !== $year,
                    ])
                    @if ($option === $year) aria-current="page" @endif>{{ $option }}</a>
            @endforeach
        </div>
    </nav>

    <x-ui.card :padding="$charges->isEmpty()">
        @if ($charges->isEmpty())
            <x-ui.empty-state icon="hand-coins" :title="__('No dues set for :year', ['year' => $year])" :description="__('Set yearly dues or a levy for the schools under you. You can check it before issuing it to schools.')">
                <x-ui.button :href="route('membership.charges.create')" icon="plus">{{ __('New charge') }}</x-ui.button>
            </x-ui.empty-state>
        @else
            <ul class="divide-y divide-stone-100">
                @foreach ($charges as $charge)
                    @php
                        $rate = $charge->expected_kobo > 0 ? (int) round($charge->collected_kobo / $charge->expected_kobo * 100) : 0;
                    @endphp
                    <li>
                        <a href="{{ route('membership.charges.show', $charge) }}" class="block px-5 py-4 hover:bg-stone-50">
                            <span class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                                <span class="min-w-0">
                                    <span class="block font-semibold text-stone-900">{{ $charge->title }}</span>
                                    <span class="block text-sm text-stone-500">{{ $charge->placeName() }} · {{ $charge->kind->label() }} · {{ __('due :date', ['date' => $charge->due_date->format('j M Y')]) }}</span>
                                </span>
                                <span class="flex items-center gap-2">
                                    <span class="font-semibold text-stone-900 tabular">{{ \App\Support\Money::format($charge->amount_kobo) }}</span>
                                    @if ($charge->isCancelled())
                                        <x-ui.badge>{{ __('Withdrawn') }}</x-ui.badge>
                                    @elseif (! $charge->isIssued())
                                        <x-ui.badge tone="warning">{{ __('Draft') }}</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="success">{{ __('Issued') }}</x-ui.badge>
                                    @endif
                                </span>
                            </span>
                            @if ($charge->isIssued())
                                <span class="mt-3 flex items-center gap-3 text-sm text-stone-600">
                                    <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                                        <span class="block h-full rounded-full bg-brand-600" style="width: {{ $rate }}%"></span>
                                    </span>
                                    <span class="shrink-0 tabular">{{ __(':paid of :billed paid · :collected', ['paid' => number_format($charge->paid_count), 'billed' => number_format($charge->billed_count), 'collected' => \App\Support\Money::format((int) $charge->collected_kobo)]) }}</span>
                                </span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</x-layouts.app>
