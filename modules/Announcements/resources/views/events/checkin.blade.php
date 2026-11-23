<x-layouts.app :title="__('Event check-in')">
    <div class="mx-auto max-w-lg">
        <x-ui.card class="text-center">
            @php
                $when = $checkin?->checked_in_at?->timezone(config('app.timezone'))->format('g:ia, j M');
            @endphp

            @if ($state === 'ok')
                <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                    <x-ui.icon name="circle-check" size="size-7" />
                </div>
                <h1 class="mt-4 text-xl font-semibold text-stone-900">{{ ($staff ?? false) ? __(':name is checked in', ['name' => $attendee->name]) : __("You're checked in") }}</h1>
                <p class="mt-1 text-sm text-stone-500">{{ $event->title }} · {{ $when }}</p>
            @elseif ($state === 'already')
                <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-sky-50 text-sky-700 ring-1 ring-sky-200">
                    <x-ui.icon name="user-check" size="size-7" />
                </div>
                <h1 class="mt-4 text-xl font-semibold text-stone-900">{{ ($staff ?? false) ? __(':name was already checked in', ['name' => $attendee->name]) : __("You're already checked in") }}</h1>
                <p class="mt-1 text-sm text-stone-500">{{ $event->title }} · {{ __('first scan at :time', ['time' => $when]) }}</p>
            @else
                <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-700 ring-1 ring-amber-200">
                    <x-ui.icon name="clock" size="size-7" />
                </div>
                <h1 class="mt-4 text-xl font-semibold text-stone-900">{{ __('Check-in is closed') }}</h1>
                <p class="mt-1 text-sm text-stone-500">
                    {{ __('Check-in for :event runs from :from to :to.', [
                        'event' => $event->title,
                        'from' => $event->checkInOpensAt()->timezone(config('app.timezone'))->format('g:ia, j M'),
                        'to' => $event->checkInClosesAt()->timezone(config('app.timezone'))->format('g:ia, j M'),
                    ]) }}
                </p>
            @endif

            <div class="mt-6 flex flex-wrap justify-center gap-2">
                <x-ui.button :href="route('events.show', $event)" variant="secondary">{{ __('Event details') }}</x-ui.button>
                @if ($staff ?? false)
                    <x-ui.button :href="route('events.attendance', $event)">{{ __('Attendance list') }}</x-ui.button>
                @endif
            </div>
        </x-ui.card>
    </div>
</x-layouts.app>
