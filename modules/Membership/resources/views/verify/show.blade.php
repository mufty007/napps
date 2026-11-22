<x-layouts.public :title="$school?->name ?? __('Not found')">
    <section class="mx-auto max-w-2xl px-4 py-12 sm:px-6 sm:py-16">
        @if (! $school)
            <div class="rounded-3xl bg-white p-6 ring-1 ring-stone-200 sm:p-8">
                <span class="flex size-12 items-center justify-center rounded-2xl bg-red-50 text-red-700 ring-1 ring-red-100"><x-ui.icon name="shield-x" size="size-6" /></span>
                <h1 class="mt-5 text-2xl font-semibold tracking-tight text-stone-900">{{ __('No member school with this number') }}</h1>
                <p class="mt-2 text-stone-600">{{ __(':number is not the number of a :org member school. Check the certificate, or search by the school\'s name.', ['number' => $number, 'org' => config('napps.name')]) }}</p>
            </div>
        @else
            @php $suspended = $school->membership_status === \App\Enums\MembershipStatus::Suspended; @endphp
            <div class="overflow-hidden rounded-3xl bg-white ring-1 ring-stone-200">
                <div @class(['px-6 py-5 sm:px-8', 'bg-brand-700 text-white' => ! $suspended, 'bg-red-700 text-white' => $suspended])>
                    <p class="flex items-center gap-2 text-sm font-semibold">
                        <x-ui.icon :name="$suspended ? 'shield-alert' : 'shield-check'" />
                        {{ $suspended ? __('Membership suspended') : __('Verified :org member school', ['org' => config('napps.name')]) }}
                    </p>
                </div>
                <div class="p-6 sm:p-8">
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-900">{{ $school->name }}</h1>
                    <dl class="mt-6 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-stone-500">{{ __('Membership number') }}</dt>
                            <dd class="mt-0.5 font-semibold text-stone-900">{{ $school->membership_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-stone-500">{{ __('Member since') }}</dt>
                            <dd class="mt-0.5 font-semibold text-stone-900">{{ $memberSince->format('F Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-stone-500">{{ __('Chapter') }}</dt>
                            <dd class="mt-0.5 font-semibold text-stone-900">{{ $school->chapter?->name }}, {{ $school->state?->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-stone-500">{{ __('Levels') }}</dt>
                            <dd class="mt-0.5 font-semibold capitalize text-stone-900">{{ collect($school->levels)->implode(', ') ?: '—' }}</dd>
                        </div>
                        @if ($school->address)
                            <div class="sm:col-span-2">
                                <dt class="text-stone-500">{{ __('Address') }}</dt>
                                <dd class="mt-0.5 font-semibold text-stone-900">{{ $school->address }}{{ $school->lga ? ', '.$school->lga : '' }}</dd>
                            </div>
                        @endif
                    </dl>
                    @if ($suspended)
                        <p class="mt-6 rounded-xl bg-red-50 p-4 text-sm text-red-900 ring-1 ring-red-200">{{ __('This school is registered with NAPPS but its membership is currently suspended. Ask the school or the chapter for details.') }}</p>
                    @endif
                    <p class="mt-6 text-xs text-stone-500">{{ __('Checked :date. Status shown live from the NAPPS register.', ['date' => now()->format('j M Y, g:ia')]) }}</p>
                </div>
            </div>
        @endif

        <a href="{{ route('verify.index') }}" class="mt-6 flex min-h-11 items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
            <x-ui.icon name="search" size="size-4" /> {{ __('Check another school') }}
        </a>
    </section>
</x-layouts.public>
