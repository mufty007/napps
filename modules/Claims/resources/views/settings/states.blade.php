<x-layouts.app :title="__('Claims by state')">
    <x-ui.page-header
        :eyebrow="__('Legal')"
        :title="__('Debt claims by state')"
        :description="__('Switch claims on for a state once a barrister has confirmed the legal arrangements there. Schools in other states cannot open claims.')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('claims.index')" variant="secondary" icon="arrow-left">{{ __('All claims') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($states as $state)
            @php $setting = $settings[$state->id] ?? null; @endphp
            <section class="rounded-2xl bg-white p-5 ring-1 ring-stone-200" aria-labelledby="state-{{ $state->id }}">
                <header class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 id="state-{{ $state->id }}" class="text-base font-semibold text-stone-900">{{ $state->name }}</h2>
                        <p class="text-sm text-stone-500">{{ trans_choice(':count school|:count schools', (int) ($schoolCounts[$state->id] ?? 0)) }}</p>
                    </div>
                    <x-ui.badge :tone="$setting?->enabled ? 'success' : 'neutral'" dot>{{ $setting?->enabled ? __('On') : __('Off') }}</x-ui.badge>
                </header>

                <form method="POST" action="{{ route('claims.settings.states.update', $state) }}" class="mt-4 space-y-3" x-data="ajaxForm()" @submit.prevent="submit($el)">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="enabled" value="0">
                    <input type="hidden" name="barrister_confirmed" value="0">

                    <x-ui.checkbox :id="'confirmed-'.$state->id" name="barrister_confirmed" :checked="(bool) $setting?->barrister_confirmed_at" :label="__('A barrister has confirmed the arrangements')" />
                    @if ($setting?->barrister_confirmed_at)
                        <p class="-mt-2 pl-8 text-xs text-stone-500">{{ __('Confirmed :date', ['date' => $setting->barrister_confirmed_at->format('j M Y')]) }}</p>
                    @endif
                    <x-ui.checkbox :id="'enabled-'.$state->id" name="enabled" :checked="(bool) $setting?->enabled" :label="__('Allow schools in :state to open claims', ['state' => $state->name])" />

                    <div class="space-y-1.5">
                        <label for="notes-{{ $state->id }}" class="block text-sm font-medium text-stone-800">{{ __('Notes') }}</label>
                        <textarea id="notes-{{ $state->id }}" name="notes" rows="2" maxlength="2000"
                            class="block w-full rounded-xl border-0 py-2.5 text-base text-stone-900 shadow-sm ring-1 ring-inset ring-stone-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm"
                            placeholder="{{ __('Barrister name, chambers, court arrangements…') }}">{{ $setting?->notes }}</textarea>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <x-ui.button type="submit" variant="secondary" icon-right="check">{{ __('Save') }}</x-ui.button>
                        @if ($setting?->updater)
                            <span class="text-xs text-stone-500">{{ __('Updated by :name', ['name' => $setting->updater->name]) }}</span>
                        @endif
                    </div>
                </form>
            </section>
        @endforeach
    </div>
</x-layouts.app>
