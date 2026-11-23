<x-layouts.app :title="__('Schools')">
    <x-ui.page-header :eyebrow="__('Membership')" :title="__('Member schools')" :description="__('Every member school in your area and its dues standing.')" />

    <div x-data="ajaxFilter({ target: '[data-ajax-results]' })">
        <x-ui.filter-bar method="GET" :action="route('membership.schools.index')" class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))_auto] lg:items-end"
            @submit.prevent="fromForm($el)"
            @input.debounce.300ms="if ($event.target.name === 'q') fromForm($event.target.form)"
            @change="if ($event.target.matches('select')) fromForm($event.target.form)">
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
        </x-ui.filter-bar>

        <div class="relative">
            <div x-show="busy" x-cloak class="absolute inset-0 z-10 flex items-start justify-center bg-white/60 pt-10">
                <x-ui.skeleton :lines="4" class="w-full max-w-xl" />
            </div>
            @include('membership::schools.partials.table')
        </div>
    </div>
</x-layouts.app>
