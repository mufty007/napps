<x-layouts.app :title="__('Fees')">
    <x-ui.page-header
        :eyebrow="__('Fees')"
        :title="__('Invoices')"
        :description="__('What families owe your school this term, and what has been paid.')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('fees.agreements.index')" variant="ghost" icon="file-signature">{{ __('Agreements') }}</x-ui.button>
            <x-ui.button :href="route('fees.structures.index')" variant="secondary" icon="list-checks">{{ __('Fee structures') }}</x-ui.button>
            @if ($canCreate)
                <x-ui.button :href="route('fees.invoices.create')" icon="plus">{{ __('Raise invoices') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (! $showSchool)
        <x-ui.card :title="__('Arrears by age')" :description="__('Money still owed, by how long it has been overdue.')" class="mb-6">
            @php
                $total = (int) collect($ageing)->sum('kobo');
                $max = max(1, (int) collect($ageing)->max('kobo'));
                $colors = ['current' => 'bg-sky-500', '1-30' => 'bg-amber-400', '31-60' => 'bg-amber-500', '61-90' => 'bg-orange-600', '90+' => 'bg-red-600'];
            @endphp
            @if ($total === 0)
                <p class="text-sm text-stone-500">{{ __('Nothing is owed right now.') }}</p>
            @else
                <ul class="space-y-3">
                    @foreach ($ageing as $bucket)
                        <li class="grid grid-cols-[6.5rem_minmax(0,1fr)_auto] items-center gap-3 text-sm">
                            <span class="text-stone-600">{{ $bucket['label'] }}</span>
                            <span class="h-2.5 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                                <span class="block h-full rounded-full {{ $colors[$bucket['key']] }}" style="width: {{ $bucket['kobo'] > 0 ? max(3, round($bucket['kobo'] / $max * 100)) : 0 }}%"></span>
                            </span>
                            <span class="text-right font-semibold tabular text-stone-900">{{ \App\Support\Money::format($bucket['kobo']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    @endif

    {{-- Tabs and the pager are links inside the results block; one delegated click handler loads them in place. --}}
    <div
        x-data="ajaxFilter({ target: '[data-ajax-results]' })"
        @click="const a = $event.target.closest('[data-ajax-tab], [data-ajax-results] nav[role=navigation] a'); if (a && a.href) { $event.preventDefault(); load(a.href) }"
    >
        <x-ui.filter-bar
            method="GET"
            :action="route('fees.invoices.index')"
            class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
            @submit.prevent="const u = new URL(window.location.href); u.searchParams.set('q', $el.q.value); u.searchParams.delete('page'); load(u.toString())"
            @input.debounce.300ms="if ($event.target.name === 'q') { const u = new URL(window.location.href); u.searchParams.set('q', $event.target.value); u.searchParams.delete('page'); load(u.toString()) }"
        >
            <input type="hidden" name="status" value="{{ $status }}">
            <x-ui.input name="q" type="search" :label="__('Search')" :value="$search" :placeholder="__('Child, parent, phone or reference')" autocomplete="off" />
            <x-ui.button type="submit" variant="secondary" icon="search">{{ __('Search') }}</x-ui.button>
        </x-ui.filter-bar>

        <div class="relative">
            <div x-show="busy" x-cloak class="absolute inset-0 z-10 flex items-start justify-center bg-white/60 pt-10">
                <x-ui.skeleton :lines="4" class="w-full max-w-xl" />
            </div>
            @include('fees::invoices.partials.results')
        </div>
    </div>
</x-layouts.app>
