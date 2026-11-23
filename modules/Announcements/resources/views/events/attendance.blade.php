<x-layouts.app :title="__('Attendance · :title', ['title' => $event->title])">
    <x-ui.page-header :eyebrow="__('Event attendance')" :title="$event->title" :description="$event->starts_at->timezone(config('app.timezone'))->format('l, j F Y · g:ia')">
        <x-slot:actions>
            <x-ui.button :href="route('events.edit', $event)" variant="secondary" icon="pencil">{{ __('Edit event') }}</x-ui.button>
            <x-ui.button :href="route('events.manage')" variant="ghost">{{ __('All events') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat :label="__('Going')" :value="$counts['going'] ?? 0" icon="calendar-check" />
        <x-ui.stat :label="__('Maybe')" :value="$counts['maybe'] ?? 0" icon="users-round" />
        <x-ui.stat :label="__('Can\'t go')" :value="$counts['declined'] ?? 0" icon="users-round" />
        <x-ui.stat :label="__('Checked in')" :value="$checkins->count()" icon="user-check" />
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <x-ui.table>
            <x-slot:toolbar><span class="font-semibold text-stone-900">{{ trans_choice(':count RSVP|:count RSVPs', $rsvps->count()) }}</span></x-slot:toolbar>
            <x-slot:head>
                <th>{{ __('Name') }}</th>
                <th>{{ __('RSVP') }}</th>
                <th>{{ __('Check-in') }}</th>
            </x-slot:head>
            @forelse ($rsvps as $rsvp)
                @php $arrived = $checkedInIds->has($rsvp->user_id); @endphp
                <tr>
                    <td>
                        <p class="font-semibold text-stone-900">{{ $rsvp->user->name }}</p>
                        <p class="text-xs text-stone-500">{{ \App\Support\PhoneNumber::format($rsvp->user->phone) }}</p>
                    </td>
                    <td><x-ui.badge :tone="$rsvp->status === 'going' ? 'success' : ($rsvp->status === 'maybe' ? 'warning' : 'neutral')">{{ $rsvp->statusLabel() }}</x-ui.badge></td>
                    <td>
                        @if ($arrived)
                            <x-ui.badge tone="success" dot>{{ __('Arrived') }}</x-ui.badge>
                        @elseif ($rsvp->status !== 'declined')
                            <form method="POST" action="{{ route('events.attendance.checkin', [$event, $rsvp->user_id]) }}">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" size="sm" class="min-h-11">{{ __('Check in') }}</x-ui.button>
                            </form>
                        @else
                            <span class="text-stone-400">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-stone-500">{{ __('No RSVPs yet.') }}</td></tr>
            @endforelse
        </x-ui.table>

        <div class="space-y-4">
            <x-ui.card :title="__('Venue QR code')">
                @if ($venueUrl)
                    <div class="mx-auto w-fit rounded-2xl bg-white p-3 ring-1 ring-stone-200 [&_svg]:size-48" role="img" aria-label="{{ __('Venue check-in QR code') }}">{!! \App\Support\QrCode::svg($venueUrl, 192) !!}</div>
                    <p class="mt-3 text-sm text-stone-500">{{ __('Display this at the door. Members scan it with their phone camera while signed in. Active from :from to :to.', [
                        'from' => $event->checkInOpensAt()->timezone(config('app.timezone'))->format('g:ia, j M'),
                        'to' => $event->checkInClosesAt()->timezone(config('app.timezone'))->format('g:ia, j M'),
                    ]) }}</p>
                @else
                    <p class="text-sm text-stone-500">{{ __('Check-in for this event has ended.') }}</p>
                @endif
            </x-ui.card>
            <p class="px-1 text-xs text-stone-500">{{ __('You can also scan the pass QR code on an attendee\'s phone. Opening it in your signed-in browser checks them in.') }}</p>
        </div>
    </div>
</x-layouts.app>
