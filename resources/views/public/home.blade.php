<x-layouts.public>
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-brand-900 text-white">
        <div aria-hidden="true" class="absolute -right-40 -top-40 size-[36rem] rounded-full bg-brand-600/30 blur-3xl"></div>
        <div aria-hidden="true" class="absolute -bottom-48 left-1/3 size-[30rem] rounded-full bg-accent-500/10 blur-3xl"></div>

        <div class="relative mx-auto grid max-w-6xl gap-12 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:items-center">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-brand-100 ring-1 ring-white/15">
                    <x-ui.icon name="badge-check" size="size-4" />
                    {{ __('For verified NAPPS member schools') }}
                </p>
                <h1 class="mt-6 text-4xl font-semibold leading-[1.1] tracking-tight sm:text-5xl">
                    {{ __('Collect your fees. Keep your records. Stand together.') }}
                </h1>
                <p class="mt-5 max-w-xl text-lg text-brand-100">
                    {{ __('One platform for NAPPS schools to bill parents, chase arrears, file claims, track leases and run the association from chapter to national.') }}
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <x-ui.button :href="route('register')" variant="accent" size="lg" icon-right="arrow-right">{{ __('Create an account') }}</x-ui.button>
                    <x-ui.button :href="route('login')" size="lg" class="bg-white/10 ring-1 ring-inset ring-white/25 hover:bg-white/20">{{ __('Sign in') }}</x-ui.button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3" aria-hidden="true">
                @foreach ([
                    ['wallet', __('Online fee payments'), __('Card, transfer, USSD')],
                    ['bell', __('Automatic reminders'), __('SMS and WhatsApp')],
                    ['scale', __('Claim-ready files'), __('Reviewed by the legal panel')],
                    ['messages-square', __('Members community'), __('Chapter to national')],
                ] as $i => [$icon, $title, $text])
                    <div x-reveal="{{ 80 + $i * 50 }}" class="rounded-2xl bg-white/[0.07] p-5 ring-1 ring-white/10">
                        <x-ui.icon :name="$icon" class="text-accent-300" />
                        <p class="mt-4 font-semibold">{{ $title }}</p>
                        <p class="mt-1 text-sm text-brand-200">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Modules --}}
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold text-brand-700">{{ __('What members get') }}</p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-stone-900">{{ __('Built around the problems proprietors raised') }}</h2>
        </div>

        <div class="mt-10 grid gap-px overflow-hidden rounded-3xl bg-stone-200 ring-1 ring-stone-200 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['receipt', __('Fees & collections'), __('Bulk term invoices, a virtual account for every family, instalment plans and an arrears report you can act on.')],
                ['gavel', __('Debt claims'), __('Turn a 90-day debt into a complete claim pack, then track it from demand letter to judgment.')],
                ['folder', __('Records & retention'), __('Student and staff files that move with the pupil, with transfer clearance between member schools.')],
                ['building-2', __('Facilities & leases'), __('Lease register, renewal reminders and a document vault, so no school loses its premises by surprise.')],
                ['megaphone', __('Announcements & events'), __('Circulars that reach every school by app and SMS, with read receipts, RSVPs and AGM voting.')],
                ['clipboard-list', __('Surveys & templates'), __('Needs surveys with results by chapter and state, and a library of legally reviewed documents.')],
            ] as $i => [$icon, $title, $text])
                <div x-reveal="{{ $i * 40 }}" class="bg-white p-6 sm:p-8">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-brand-50 text-brand-700"><x-ui.icon :name="$icon" /></span>
                    <h3 class="mt-5 font-semibold text-stone-900">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-stone-600">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Low bandwidth --}}
    <section class="border-y border-stone-200 bg-stone-50">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-14 sm:px-6 md:grid-cols-3">
            @foreach ([
                ['smartphone', __('Made for phones'), __('Light pages that load on 3G and work on cheap Android phones.')],
                ['lock', __('Private by design'), __('Each level sees only its own data. Built to the NDPA 2023.')],
                ['network', __('Your chapter, your data'), __('Chapters and states keep their own dashboards, with dues split automatically.')],
            ] as [$icon, $title, $text])
                <div class="flex gap-4" x-reveal>
                    <x-ui.icon :name="$icon" class="mt-0.5 text-brand-700" />
                    <div>
                        <p class="font-semibold text-stone-900">{{ $title }}</p>
                        <p class="mt-1 text-sm text-stone-600">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.public>
