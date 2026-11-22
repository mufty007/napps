@php
    $percent = $dues['expected'] > 0 ? (int) round($dues['collected'] / $dues['expected'] * 100) : 0;
@endphp

<div class="mt-6 space-y-6">
    @if ($reviewQueue && $reviewQueue['count'] > 0)
        <a
            href="{{ route('membership.applications.index', ['status' => $reviewQueue['status']->value]) }}"
            class="flex min-h-16 items-center gap-4 rounded-2xl bg-accent-100 px-5 py-4 ring-1 ring-inset ring-accent-300 transition-colors hover:bg-accent-300/40"
            x-reveal
        >
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-accent-600 ring-1 ring-accent-300">
                <x-ui.icon name="clipboard-check" />
            </span>
            <span class="min-w-0 flex-1 text-sm">
                <span class="block font-semibold text-stone-900">
                    {{ trans_choice(
                        $reviewQueue['status'] === \Modules\Membership\Enums\ApplicationStatus::Submitted
                            ? ':count school is waiting for your verification|:count schools are waiting for your verification'
                            : ':count school is waiting for your approval|:count schools are waiting for your approval',
                        $reviewQueue['count'],
                    ) }}
                </span>
                <span class="block text-stone-600">{{ __('Review the details and documents, then approve or reject with a reason.') }}</span>
            </span>
            <x-ui.icon name="arrow-right" class="text-stone-500" />
        </a>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Dues :year', ['year' => $duesYear])" :description="__('Collected against what was billed to schools in your area.')" x-reveal="40">
            @can('membership.dues.configure')
                <x-slot:actions>
                    <x-ui.button :href="route('membership.charges.index')" variant="ghost" size="sm" icon-right="arrow-right">{{ __('Dues') }}</x-ui.button>
                </x-slot:actions>
            @endcan

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-sm text-stone-500">{{ __('Collected') }}</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900 tabular">{{ \App\Support\Money::format($dues['collected']) }}</p>
                </div>
                <p class="text-sm text-stone-500 tabular">{{ __('of :amount expected', ['amount' => \App\Support\Money::format($dues['expected'])]) }}</p>
            </div>

            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="{{ __('Share of dues collected') }}">
                <div class="h-full rounded-full bg-brand-600" style="width: {{ $percent }}%"></div>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-stone-500">{{ __('Collection rate') }}</dt>
                    <dd class="font-semibold text-stone-900 tabular">{{ $percent }}%</dd>
                </div>
                @if ($levelShare)
                    <div>
                        <dt class="text-stone-500">{{ $levelShare['label'] }}</dt>
                        <dd class="font-semibold text-stone-900 tabular">{{ \App\Support\Money::format($levelShare['kobo']) }}</dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>

        @if ($rankingBy)
            <x-ui.card
                :title="$rankingBy === 'state' ? __('Compliance by state') : __('Chapters by compliance')"
                :description="__('Share of member schools with dues paid up.')"
                :padding="$ranking->isEmpty()"
                x-reveal="80"
            >
                @if ($ranking->isEmpty())
                    <x-ui.empty-state icon="chart-column" :title="__('No member schools yet')" class="py-4" />
                @else
                    <ol class="divide-y divide-stone-100">
                        @foreach ($ranking->take(8) as $row)
                            <li>
                                <a href="{{ route('membership.schools.index', [$rankingBy => $row['id']]) }}" class="block px-5 py-3 hover:bg-stone-50">
                                    <span class="flex items-center justify-between gap-3 text-sm">
                                        <span class="min-w-0 truncate font-medium text-stone-900">{{ $row['name'] }}</span>
                                        <span class="shrink-0 text-stone-500 tabular">{{ __(':active of :members', ['active' => $row['active'], 'members' => $row['members']]) }} · <span class="font-semibold text-stone-900">{{ $row['percent'] }}%</span></span>
                                    </span>
                                    <span class="mt-2 block h-1.5 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                                        <span @class([
                                            'block h-full rounded-full',
                                            'bg-emerald-500' => $row['percent'] >= 80,
                                            'bg-amber-400' => $row['percent'] >= 50 && $row['percent'] < 80,
                                            'bg-red-500' => $row['percent'] < 50,
                                        ]) style="width: {{ $row['percent'] }}%"></span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>
        @else
            <x-ui.card :title="__('Follow up on dues')" :description="__('Schools in your chapter that are owing or suspended.')" :padding="$followUp->isEmpty()" x-reveal="80">
                @if ($followUp->isEmpty())
                    <x-ui.empty-state icon="badge-check" :title="__('Every school is paid up')" class="py-4" />
                @else
                    <ul class="divide-y divide-stone-100">
                        @foreach ($followUp as $school)
                            <li>
                                <a href="{{ route('membership.schools.show', $school) }}" class="flex min-h-14 flex-wrap items-center justify-between gap-x-4 gap-y-1 px-5 py-3 hover:bg-stone-50">
                                    <span class="min-w-0 truncate text-sm font-semibold text-stone-900">{{ $school->name }}</span>
                                    <span class="flex items-center gap-2">
                                        <span class="text-sm text-stone-600 tabular">{{ \App\Support\Money::format($school->outstanding_kobo) }}</span>
                                        <x-ui.badge :tone="$school->membership_status->tone()">{{ $school->membership_status->label() }}</x-ui.badge>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endif
    </div>
</div>
