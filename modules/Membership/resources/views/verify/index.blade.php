<x-layouts.public>
    <section class="mx-auto max-w-2xl px-4 py-12 sm:px-6 sm:py-16">
        <span class="flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-700 ring-1 ring-brand-100"><x-ui.icon name="shield-check" size="size-6" /></span>
        <h1 class="mt-5 text-3xl font-semibold tracking-tight text-stone-900">{{ __('Verify a member school') }}</h1>
        <p class="mt-2 text-stone-600">{{ __('Check that a private school is a registered member of :org. Search by the school\'s name or the membership number on its certificate.', ['org' => config('napps.full_name')]) }}</p>

        <form method="GET" action="{{ route('verify.index') }}" class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-end" role="search">
            <x-ui.input name="q" type="search" :label="__('School name or membership number')" :value="$query" class="flex-1" placeholder="NAPPS-KN-00012" minlength="3" required />
            <x-ui.button type="submit" icon="search" size="lg">{{ __('Search') }}</x-ui.button>
        </form>

        @if (mb_strlen($query) >= 3)
            <div class="mt-8" aria-live="polite">
                @if ($results->isEmpty())
                    <x-ui.alert tone="warning" :title="__('No member school found')">
                        {{ __('Check the spelling or the number on the certificate. A school that is not listed here is not a NAPPS member.') }}
                    </x-ui.alert>
                @else
                    <p class="text-sm text-stone-500">{{ trans_choice(':count member school found|:count member schools found', $results->count()) }}</p>
                    <ul class="mt-3 divide-y divide-stone-100 overflow-hidden rounded-2xl bg-white ring-1 ring-stone-200">
                        @foreach ($results as $school)
                            <li>
                                <a href="{{ route('verify.show', $school->membership_number) }}" class="flex min-h-16 items-center justify-between gap-4 px-5 py-3.5 hover:bg-stone-50">
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-stone-900">{{ $school->name }}</span>
                                        <span class="block truncate text-sm text-stone-500">{{ $school->lga ? $school->lga.', ' : '' }}{{ $school->state?->name }} · {{ $school->membership_number }}</span>
                                    </span>
                                    <x-ui.icon name="chevron-right" class="text-stone-400" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </section>
</x-layouts.public>
