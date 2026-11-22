<x-layouts.app :title="__('Home')">
    <x-ui.page-header
        :eyebrow="$level?->label().($place ? ' · '.$place->name : '')"
        :title="__('Good :part, :name', ['part' => now()->hour < 12 ? __('morning') : (now()->hour < 17 ? __('afternoon') : __('evening')), 'name' => Str::before(auth()->user()->name, ' ')])"
        :description="__('Member schools and their standing across your area.')"
    />

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat x-reveal :label="__('Member schools')" :value="$schoolCount" icon="school" />
        <x-ui.stat x-reveal="40" :label="__('Active')" :value="$statusCounts['active']" icon="badge-check" />
        <x-ui.stat x-reveal="80" :label="__('Owing dues')" :value="$statusCounts['owing']" icon="hand-coins" />
        @if ($stateCount !== null)
            <x-ui.stat x-reveal="120" :label="__('States')" :value="$stateCount" icon="map" :hint="__(':count chapters', ['count' => number_format($chapterCount)])" />
        @elseif ($chapterCount !== null)
            <x-ui.stat x-reveal="120" :label="__('Chapters')" :value="$chapterCount" icon="network" />
        @else
            <x-ui.stat x-reveal="120" :label="__('Pending approval')" :value="$statusCounts['pending']" icon="clipboard-list" />
        @endif
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <x-ui.card :title="__('Recently added schools')" :padding="$recentSchools->isEmpty()" x-reveal="160">
            @if ($recentSchools->isEmpty())
                <x-ui.empty-state icon="school" :title="__('No member schools yet')" :description="__('Schools appear here once they apply and are added to a chapter.')" />
            @else
                <ul class="divide-y divide-stone-100">
                    @foreach ($recentSchools as $school)
                        <li class="flex items-center justify-between gap-4 px-5 py-3.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-stone-900">{{ $school->name }}</p>
                                <p class="truncate text-sm text-stone-500">{{ $school->chapter?->name }}</p>
                            </div>
                            <x-ui.badge :tone="$school->membership_status->tone()" dot>{{ $school->membership_status->label() }}</x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Dues compliance')" x-reveal="200">
            @php $total = max($schoolCount, 1); @endphp
            <div class="flex h-2.5 overflow-hidden rounded-full bg-stone-100" role="img" aria-label="{{ __('Share of schools by membership status') }}">
                <span class="bg-emerald-500" style="width: {{ $statusCounts['active'] / $total * 100 }}%"></span>
                <span class="bg-amber-400" style="width: {{ $statusCounts['owing'] / $total * 100 }}%"></span>
                <span class="bg-red-500" style="width: {{ $statusCounts['suspended'] / $total * 100 }}%"></span>
            </div>
            <dl class="mt-5 space-y-3 text-sm">
                @foreach (\App\Enums\MembershipStatus::cases() as $status)
                    <div class="flex items-center justify-between">
                        <dt><x-ui.badge :tone="$status->tone()" dot>{{ $status->label() }}</x-ui.badge></dt>
                        <dd class="font-semibold text-stone-900 tabular">{{ number_format($statusCounts[$status->value]) }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>
    </div>
</x-layouts.app>
