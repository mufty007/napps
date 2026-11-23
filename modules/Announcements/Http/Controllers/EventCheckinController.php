<?php

namespace Modules\Announcements\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Announcements\Models\Event;
use Modules\Announcements\Models\EventCheckin;
use Modules\Announcements\Models\EventRsvp;

/**
 * QR check-in. Two signed links, both opened by a signed-in member:
 *  - the venue QR ("self"): the attendee scans the code shown at the door;
 *  - the attendee pass ("scan"): staff scan the QR on a going member's phone.
 * Signatures stop anyone forging a check-in by editing the URL.
 */
class EventCheckinController extends Controller
{
    public function self(Request $request, Event $event): View|Response
    {
        abort_unless($event->isPublished(), 404);

        if (! $event->checkInIsOpen()) {
            return response()->view('announcements::events.checkin', [
                'event' => $event, 'state' => 'closed', 'attendee' => $request->user(), 'checkin' => null,
            ], 403);
        }

        Gate::authorize('checkIn', $event);

        [$checkin, $created] = $this->record($event, $request->user(), null);

        return view('announcements::events.checkin', [
            'event' => $event,
            'state' => $created ? 'ok' : 'already',
            'attendee' => $request->user(),
            'checkin' => $checkin,
        ]);
    }

    public function scan(Request $request, Event $event, User $user): View
    {
        Gate::authorize('events.manage');
        abort_unless($event->isPublished(), 404);

        [$checkin, $created] = $this->record($event, $user, $request->user());

        return view('announcements::events.checkin', [
            'event' => $event,
            'state' => $created ? 'ok' : 'already',
            'attendee' => $user,
            'checkin' => $checkin,
            'staff' => true,
        ]);
    }

    /** Organisers: who is coming, who has arrived, and the venue QR. */
    public function attendance(Event $event): View
    {
        Gate::authorize('events.manage');

        $rsvps = $event->rsvps()->with('user:id,name,phone')->latest('updated_at')->get();
        $checkins = $event->checkins()->with('user:id,name,phone')->latest('checked_in_at')->get();

        $venueUrl = $event->checkInClosesAt()->isFuture()
            ? URL::temporarySignedRoute('events.checkin.self', $event->checkInClosesAt(), ['event' => $event])
            : null;

        return view('announcements::events.attendance', [
            'event' => $event,
            'rsvps' => $rsvps,
            'counts' => $rsvps->countBy('status'),
            'checkins' => $checkins,
            'checkedInIds' => $checkins->pluck('user_id')->flip(),
            'venueUrl' => $venueUrl,
        ]);
    }

    /** Organisers tick someone off by hand (no camera, flat battery...). */
    public function manual(Request $request, Event $event, User $user): RedirectResponse
    {
        Gate::authorize('events.manage');
        abort_unless($event->rsvps()->where('user_id', $user->id)->exists(), 404);

        $this->record($event, $user, $request->user());

        return redirect()->route('events.attendance', $event)->with('status', __(':name checked in.', ['name' => $user->name]));
    }

    /**
     * Idempotent: a second scan keeps the first arrival time.
     *
     * @return array{0: EventCheckin, 1: bool} [check-in, created now?]
     */
    private function record(Event $event, User $user, ?User $by): array
    {
        $checkin = EventCheckin::query()->firstOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['checked_in_at' => now(), 'checked_in_by' => $by?->id],
        );

        // Turning up counts as going.
        EventRsvp::query()->updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['status' => 'going'],
        );

        return [$checkin, $checkin->wasRecentlyCreated];
    }
}
