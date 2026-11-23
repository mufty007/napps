<x-layouts.app :title="__('Claims')">
    <x-ui.page-header
        :eyebrow="__('Legal')"
        :title="__('Debt claims')"
        :description="__('Cases from demand letter through small claims court. Legal panel lawyers update the stage.')"
    >
        <x-slot:actions>
            @if ($canManageSettings)
                <x-ui.button :href="route('claims.settings.states')" variant="secondary" icon="map-pin">{{ __('State settings') }}</x-ui.button>
            @endif
            @if ($canOpen)
                <x-ui.button :href="route('claims.create')" icon="plus">{{ __('Open a claim') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Tabs, pills and the pager are links inside the results block; one delegated click handler loads them in place. --}}
    <div
        x-data="ajaxFilter({ target: '[data-ajax-results]' })"
        @click="const a = $event.target.closest('[data-ajax-tab], [data-ajax-pill], [data-ajax-results] nav[role=navigation] a'); if (a && a.href) { $event.preventDefault(); load(a.href) }"
    >
        <x-ui.filter-bar
            method="GET"
            :action="route('claims.index')"
            class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
            @submit.prevent="const u = new URL(window.location.href); u.searchParams.set('q', $el.q.value); u.searchParams.delete('page'); load(u.toString())"
            @input.debounce.300ms="if ($event.target.name === 'q') { const u = new URL(window.location.href); u.searchParams.set('q', $event.target.value); u.searchParams.delete('page'); load(u.toString()) }"
        >
            <input type="hidden" name="status" value="{{ $status }}">
            @if ($stage)<input type="hidden" name="stage" value="{{ $stage->value }}">@endif
            <x-ui.input name="q" type="search" :label="__('Search')" :value="$search" :placeholder="__('Reference, debtor, phone or school')" autocomplete="off" />
            <x-ui.button type="submit" variant="secondary" icon="search">{{ __('Search') }}</x-ui.button>
        </x-ui.filter-bar>

        <div class="relative">
            <div x-show="busy" x-cloak class="absolute inset-0 z-10 flex items-start justify-center bg-white/60 pt-10">
                <x-ui.skeleton :lines="4" class="w-full max-w-xl" />
            </div>
            @include('claims::cases.partials.results')
        </div>
    </div>
</x-layouts.app>
