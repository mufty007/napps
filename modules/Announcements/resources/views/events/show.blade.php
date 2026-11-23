<x-layouts.public :title="$event->title">
    <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-16">
        <a href="{{ route('events.index') }}" class="inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
            <x-ui.icon name="arrow-left" size="size-4" /> {{ __('All events') }}
        </a>
        <div class="mt-6 flex flex-wrap gap-2">
            <x-ui.badge tone="brand">{{ $event->kindLabel() }}</x-ui.badge>
            @if ($event->isOnline()) <x-ui.badge>{{ __('Online') }}</x-ui.badge> @endif
        </div>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">{{ $event->title }}</h1>
        <dl class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl bg-stone-50 p-4 ring-1 ring-stone-200">
                <dt class="text-xs font-semibold uppercase tracking-wider text-stone-500">{{ __('When') }}</dt>
                <dd class="mt-1 font-semibold text-stone-900">{{ $event->starts_at->timezone(config('app.timezone'))->format('l, j F Y · g:ia') }}</dd>
            </div>
            <div class="rounded-2xl bg-stone-50 p-4 ring-1 ring-stone-200">
                <dt class="text-xs font-semibold uppercase tracking-wider text-stone-500">{{ __('Where') }}</dt>
                <dd class="mt-1 font-semibold text-stone-900">{{ $event->venue ?: ($event->isOnline() ? __('Online webinar') : __('To be announced')) }}</dd>
            </div>
        </dl>
        @if ($event->summary)
            <p class="mt-6 text-lg text-stone-600">{{ $event->summary }}</p>
        @endif
        @if ($event->body)
            <div class="mt-6 whitespace-pre-wrap text-base leading-relaxed text-stone-800">{{ $event->body }}</div>
        @endif
        @if ($agenda = $event->agendaItems())
            <section class="mt-10" aria-labelledby="agenda-heading">
                <h2 id="agenda-heading" class="text-lg font-semibold text-stone-900">{{ __('Agenda') }}</h2>
                <ol class="mt-3 divide-y divide-stone-100 rounded-2xl ring-1 ring-stone-200">
                    @foreach ($agenda as $item)
                        <li class="flex gap-4 px-4 py-3">
                            <span class="w-20 shrink-0 text-sm font-semibold text-brand-700 tabular">{{ $item['time'] ?: '—' }}</span>
                            <span class="text-sm text-stone-800">{{ $item['title'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
        @if ($event->online_url)
            <x-ui.button :href="$event->online_url" class="mt-8" icon-right="external-link" target="_blank" rel="noopener">{{ __('Join online') }}</x-ui.button>
        @endif

        <section class="mt-10 rounded-2xl bg-stone-50 p-5 ring-1 ring-stone-200" aria-labelledby="rsvp-heading">
            <h2 id="rsvp-heading" class="text-lg font-semibold text-stone-900">{{ __('Will you be there?') }}</h2>
            @if ($goingCount > 0)
                <p class="mt-1 text-sm text-stone-500">{{ trans_choice(':count person is going|:count people are going', $goingCount) }}</p>
            @endif

            @if (! $user)
                <p class="mt-3 text-sm text-stone-600">{{ __('Sign in to RSVP and get your check-in pass.') }}</p>
                <x-ui.button :href="route('login')" class="mt-3" icon-right="arrow-right">{{ __('Sign in to RSVP') }}</x-ui.button>
            @elseif ($canRsvp)
                @if ($rsvp)
                    <p class="mt-2 text-sm font-medium text-stone-700">{{ __('Your answer') }}: <x-ui.badge :tone="$rsvp->status === 'going' ? 'success' : ($rsvp->status === 'maybe' ? 'warning' : 'neutral')" dot>{{ $rsvp->statusLabel() }}</x-ui.badge></p>
                @endif
                <form method="POST" action="{{ route('events.rsvp', $event) }}" class="mt-4 flex flex-wrap gap-2">
                    @csrf
                    @foreach (\Modules\Announcements\Models\EventRsvp::statusLabels() as $value => $label)
                        <button type="submit" name="status" value="{{ $value }}"
                            @class([
                                'inline-flex min-h-11 items-center justify-center rounded-xl px-4 text-sm font-semibold shadow-sm ring-1 ring-inset',
                                'bg-brand-700 text-white ring-brand-700' => $rsvp?->status === $value,
                                'bg-white text-stone-800 ring-stone-300 hover:bg-stone-50' => $rsvp?->status !== $value,
                            ])
                            @if ($rsvp?->status === $value) aria-pressed="true" @endif
                        >{{ $label }}</button>
                    @endforeach
                </form>
            @else
                <p class="mt-3 text-sm text-stone-600">{{ $event->hasEnded() ? __('This event has finished.') : __('RSVP is not open to your account for this event.') }}</p>
            @endif

            @if ($checkedIn)
                <x-ui.alert tone="success" class="mt-4">{{ __('You checked in at :time.', ['time' => $checkedIn->checked_in_at->timezone(config('app.timezone'))->format('g:ia')]) }}</x-ui.alert>
            @elseif ($passUrl)
                <div class="mt-5 flex flex-wrap items-center gap-5 border-t border-stone-200 pt-5">
                    <div class="rounded-2xl bg-white p-3 ring-1 ring-stone-200 [&_svg]:size-40" role="img" aria-label="{{ __('Your check-in QR code') }}">{!! \App\Support\QrCode::svg($passUrl, 160) !!}</div>
                    <div class="min-w-0 flex-1 text-sm text-stone-600">
                        <p class="font-semibold text-stone-900">{{ __('Your check-in pass') }}</p>
                        <p class="mt-1">{{ __('Show this code at the door and the organisers will scan it. Or scan the venue code on the day.') }}</p>
                    </div>
                </div>
            @endif
        </section>

        {{-- Minutes are for signed-in members only. --}}
        @if ($user && filled($event->minutes))
            <section class="mt-10" aria-labelledby="minutes-heading">
                <h2 id="minutes-heading" class="text-lg font-semibold text-stone-900">{{ __('Minutes') }}</h2>
                <div class="mt-3 whitespace-pre-wrap text-base leading-relaxed text-stone-800">{{ $event->minutes }}</div>
            </section>
        @endif
    </article>
</x-layouts.public>
