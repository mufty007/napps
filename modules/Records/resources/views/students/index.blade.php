<x-layouts.app :title="__('Students')">
    <x-ui.page-header
        :eyebrow="__('Records')"
        :title="__('Students')"
        :description="__('Your school\'s register: pupils, guardians and the papers filed on each record.')"
    >
        <x-slot:actions>
            @if ($canEnrol)
                <x-ui.button :href="route('records.students.create')" icon="plus">{{ __('Enrol a pupil') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Tabs and the pager are links inside the results block; one delegated click handler loads them in place. --}}
    <div
        x-data="ajaxFilter({ target: '[data-ajax-results]' })"
        @click="const a = $event.target.closest('[data-ajax-tab], [data-ajax-results] nav[role=navigation] a'); if (a && a.href) { $event.preventDefault(); load(a.href) }"
    >
        <x-ui.filter-bar
            method="GET"
            :action="route('records.students.index')"
            class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto] sm:items-end"
            @submit.prevent="const u = new URL(window.location.href); u.searchParams.set('q', $el.q.value); u.searchParams.set('class', $el.class.value); u.searchParams.delete('page'); load(u.toString())"
            @input.debounce.300ms="if ($event.target.name === 'q') { const u = new URL(window.location.href); u.searchParams.set('q', $event.target.value); u.searchParams.delete('page'); load(u.toString()) }"
            @change="if ($event.target.name === 'class') { const u = new URL(window.location.href); u.searchParams.set('class', $event.target.value); u.searchParams.delete('page'); load(u.toString()) }"
        >
            <input type="hidden" name="status" value="{{ $status->value }}">
            <x-ui.input name="q" type="search" :label="__('Search')" :value="$search" :placeholder="__('Name, admission number, guardian or phone')" autocomplete="off" />
            <x-ui.select name="class" :label="__('Class')" :value="$class" :options="$classes" :placeholder="__('All classes')" />
            <x-ui.button type="submit" variant="secondary" icon="search">{{ __('Search') }}</x-ui.button>
        </x-ui.filter-bar>

        <div class="relative">
            <div x-show="busy" x-cloak class="absolute inset-0 z-10 flex items-start justify-center bg-white/60 pt-10">
                <x-ui.skeleton :lines="4" class="w-full max-w-xl" />
            </div>
            @include('records::students.partials.results')
        </div>
    </div>
</x-layouts.app>
