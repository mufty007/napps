<x-layouts.app :title="__('Home')">
    <x-ui.page-header
        :eyebrow="$school?->chapter?->name"
        :title="$school?->name ?? __('Your school')"
        :description="__('Your school at a glance.')"
    >
        @if ($school)
            <x-slot:actions>
                <x-ui.badge :tone="$school->membership_status->tone()" dot class="text-sm">{{ __('NAPPS: :status', ['status' => $school->membership_status->label()]) }}</x-ui.badge>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($school)
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <x-ui.card :title="__('School profile')" x-reveal>
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-stone-500">{{ __('Membership number') }}</dt>
                        <dd class="mt-0.5 font-semibold text-stone-900">{{ $school->membership_number ?? __('Issued on approval') }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">{{ __('Chapter') }}</dt>
                        <dd class="mt-0.5 font-semibold text-stone-900">{{ $school->chapter?->name }}, {{ $school->state?->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">{{ __('Levels') }}</dt>
                        <dd class="mt-0.5 font-semibold capitalize text-stone-900">{{ collect($school->levels)->implode(', ') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">{{ __('Pupils') }}</dt>
                        <dd class="mt-0.5 font-semibold text-stone-900 tabular">{{ $school->pupil_count !== null ? number_format($school->pupil_count) : '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-stone-500">{{ __('Address') }}</dt>
                        <dd class="mt-0.5 font-semibold text-stone-900">{{ $school->address ?? '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Next steps')" x-reveal="60">
                <x-ui.empty-state icon="sparkles" :title="__('You are all set up')" :description="__('Fees, records, leases and claims will show their numbers here as you start using them.')" class="py-4" />
            </x-ui.card>
        </div>
    @else
        <x-ui.card>
            <x-ui.empty-state icon="school" :title="__('School not found')" :description="__('This role points to a school that no longer exists. Ask the secretariat to fix your access.')" />
        </x-ui.card>
    @endif
</x-layouts.app>
