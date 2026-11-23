<x-layouts.public>
    {{-- Hero: flat brand green, the seal, live figures as pills --}}
    <section class="bg-brand-900 text-white">
        <div class="mx-auto grid max-w-6xl gap-12 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] lg:items-center lg:py-24">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-brand-100">
                    <x-ui.icon name="badge-check" size="size-4" class="text-accent-300" />
                    {{ config('napps.motto') }}
                </p>
                <h1 class="mt-6 text-[2.5rem] font-semibold leading-[1.05] tracking-tight sm:text-6xl">
                    {{ __('Collect your fees. Keep your records. Stand together.') }}
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-brand-100">
                    {{ __('One platform for NAPPS schools to bill parents, chase arrears, file claims, track leases and run the association from chapter to national.') }}
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <x-ui.button :href="route('join')" variant="accent" size="lg" icon-right="arrow-right">{{ __('Join as a school') }}</x-ui.button>
                    <x-ui.button :href="route('login')" size="lg" class="bg-white/10 ring-1 ring-inset ring-white/25 hover:bg-white/20">{{ __('Sign in') }}</x-ui.button>
                </div>

                <dl class="mt-10 flex flex-wrap gap-2.5">
                    @foreach ([
                        [$stats['schools'], trans_choice('member school|member schools', $stats['schools'])],
                        [$stats['chapters'], trans_choice('chapter|chapters', $stats['chapters'])],
                        [$stats['states'], __('states + FCT')],
                    ] as [$value, $label])
                        <div class="flex items-baseline gap-2 rounded-full bg-white/10 px-4 py-2">
                            <dt class="sr-only">{{ $label }}</dt>
                            <dd class="text-lg font-semibold tabular text-white">{{ number_format($value) }}</dd>
                            <span class="text-sm text-brand-100" aria-hidden="true">{{ $label }}</span>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-5 text-sm text-brand-200">{{ __('Parents: ask your chapter to add you — you cannot self-register.') }}</p>
            </div>

            <div class="hidden justify-center lg:flex lg:justify-end">
                <div class="flex size-80 items-center justify-center rounded-full bg-white/5 ring-1 ring-white/10">
                    <img
                        src="{{ asset('images/napps-seal.png') }}"
                        alt="{{ config('napps.full_name') }}"
                        class="h-56 w-auto"
                        width="238"
                        height="222"
                        decoding="async"
                    >
                </div>
            </div>
        </div>
    </section>

    {{-- Public hubs --}}
    <section class="border-b border-stone-200 bg-white">
        <div class="mx-auto grid max-w-6xl gap-3 px-4 py-6 sm:grid-cols-3 sm:px-6">
            @foreach ([
                ['newspaper', __('News & updates'), __('Circulars and association news.'), 'news.index'],
                ['calendar-days', __('Events & webinars'), __('Meetings, training and AGMs.'), 'events.index'],
                ['briefcase', __('Job board'), __('Roles at member schools.'), 'jobs.index'],
            ] as $i => [$icon, $title, $text, $route])
                <a href="{{ route($route) }}" class="group flex items-center gap-4 rounded-2xl p-4 transition-colors hover:bg-stone-50" x-reveal="{{ $i * 40 }}">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-700"><x-ui.icon :name="$icon" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-stone-900">{{ $title }}</span>
                        <span class="block text-sm text-stone-500">{{ $text }}</span>
                    </span>
                    <x-ui.icon name="arrow-right" size="size-4" class="shrink-0 text-stone-300 transition-transform group-hover:translate-x-0.5 group-hover:text-brand-700" />
                </a>
            @endforeach
        </div>
    </section>

    {{-- Modules --}}
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold text-brand-700">{{ __('What members get') }}</p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">{{ __('Built around the problems proprietors raised') }}</h2>
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

    {{-- Community: show the product, not just describe it --}}
    <section class="border-y border-stone-200 bg-stone-50">
        <div class="mx-auto grid max-w-6xl gap-12 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-2 lg:items-center">
            <div>
                <p class="text-sm font-semibold text-brand-700">{{ __('Members-only community') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">{{ __('Your chapter, in your pocket') }}</h2>
                <p class="mt-4 max-w-lg text-lg leading-relaxed text-stone-600">{{ __('A private feed, forums, groups and chat for proprietors — only verified member schools can join.') }}</p>
                <ul class="mt-8 space-y-4">
                    @foreach ([
                        ['house', __('A home feed for your chapter, state and the national body, with official posts pinned to the top.')],
                        ['messages-square', __('Forums where questions get answered and the best answer is marked.')],
                        ['message-circle', __('Chat with a colleague or your whole chapter, even on a weak connection.')],
                    ] as [$icon, $text])
                        <li class="flex gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-700 text-white"><x-ui.icon :name="$icon" size="size-4" /></span>
                            <span class="pt-1.5 text-stone-700">{{ $text }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Illustration built from the real UI kit (decorative) --}}
            <div class="relative mx-auto w-full max-w-md" aria-hidden="true">
                <div class="rounded-2xl bg-white p-5 ring-1 ring-stone-200">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-800">HI</span>
                        <div class="min-w-0">
                            <p class="flex items-center gap-2 font-semibold text-stone-900">{{ __('Chapter secretariat') }} <x-ui.badge tone="brand"><x-ui.icon name="pin" size="size-3" /> {{ __('Pinned') }}</x-ui.badge></p>
                            <p class="text-xs text-stone-500">{{ __('Chapter executive · 2h') }}</p>
                        </div>
                    </div>
                    <p class="mt-4 text-[0.95rem] leading-relaxed text-stone-800">{{ __('Reminder: 2026 dues close on 31 March. Pay online from your dashboard and your certificate updates the same day.') }}</p>
                    <div class="mt-4 flex items-center gap-5 border-t border-stone-100 pt-3 text-sm font-semibold text-stone-500">
                        <span class="flex items-center gap-1.5 text-red-600"><x-ui.icon name="heart" size="size-4" fill="currentColor" /> 24</span>
                        <span class="flex items-center gap-1.5"><x-ui.icon name="message-circle" size="size-4" /> 9</span>
                    </div>
                </div>
                <div class="relative -mt-4 ml-auto w-4/5 rounded-2xl bg-brand-900 p-4 text-white sm:-mr-6">
                    <p class="text-xs font-semibold text-brand-200">{{ __('Kano Chapter · chat') }}</p>
                    <p class="mt-2 inline-block rounded-2xl rounded-bl-md bg-white/10 px-3 py-2 text-sm">{{ __('Has anyone used the new claim pack yet?') }}</p>
                    <p class="mt-2 ml-auto w-fit rounded-2xl rounded-br-md bg-accent-400 px-3 py-2 text-sm font-medium text-stone-900">{{ __('Yes — filed ours on Monday.') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- How joining works --}}
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold text-brand-700">{{ __('Joining') }}</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">{{ __('Three steps to membership') }}</h2>
            </div>
            <x-ui.button :href="route('join')" variant="secondary" icon-right="arrow-right">{{ __('How to join') }}</x-ui.button>
        </div>
        <ol class="mt-10 grid gap-4 md:grid-cols-3">
            @foreach ([
                [__('Apply online'), __('Register with your phone number, add your school details and upload your approval documents.')],
                [__('Your chapter verifies'), __('Chapter executives check your documents and may visit. You follow every step on your phone.')],
                [__('Pay dues, get certified'), __('Pay your first dues online. Your membership number and certificate are issued at once.')],
            ] as $i => [$title, $text])
                <li class="rounded-2xl bg-white p-6 ring-1 ring-stone-200" x-reveal="{{ $i * 40 }}">
                    <span class="flex size-9 items-center justify-center rounded-full bg-accent-100 text-sm font-bold text-accent-600 tabular">{{ $i + 1 }}</span>
                    <h3 class="mt-4 font-semibold text-stone-900">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-stone-600">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Join CTA --}}
    <section class="bg-brand-900 text-white">
        <div class="mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-14 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div class="max-w-xl">
                <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('Ready to bring your school into NAPPS?') }}</h2>
                <p class="mt-2 text-brand-100">{{ __('Apply through your state hub and chapter. Verification and approval happen online.') }}</p>
            </div>
            <x-ui.button :href="route('join')" variant="accent" size="lg" icon-right="arrow-right">{{ __('Join as a school') }}</x-ui.button>
        </div>
    </section>

    {{-- Low bandwidth --}}
    <section class="bg-white">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-14 sm:px-6 md:grid-cols-3">
            @foreach ([
                ['smartphone', __('Made for phones'), __('Light pages that load on 3G and work on cheap Android phones.')],
                ['lock', __('Private by design'), __('Each level sees only its own data. Built to the NDPA 2023.')],
                ['network', __('Your chapter, your data'), __('Chapters and states keep their own dashboards, with dues split automatically.')],
            ] as [$icon, $title, $text])
                <div class="flex gap-4" x-reveal>
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-stone-100 text-brand-700"><x-ui.icon :name="$icon" size="size-5" /></span>
                    <div>
                        <p class="font-semibold text-stone-900">{{ $title }}</p>
                        <p class="mt-1 text-sm text-stone-600">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.public>
