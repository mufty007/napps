<?php

namespace Modules\Announcements\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Announcements\Models\Event;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $kind = $request->string('kind')->toString();

        $events = Event::query()
            ->published()
            ->upcoming()
            ->when($kind !== '', fn ($q) => $q->where('kind', $kind))
            ->orderBy('starts_at')
            ->paginate(12)
            ->withQueryString();

        return view('announcements::events.index', compact('events', 'kind'));
    }

    public function show(Event $event): View
    {
        abort_unless($event->published_at && $event->published_at->lte(now()), 404);

        $user = auth()->user();
        $rsvp = $user ? $event->rsvps()->where('user_id', $user->id)->first() : null;
        $checkedIn = $user ? $event->checkins()->where('user_id', $user->id)->first() : null;

        return view('announcements::events.show', [
            'event' => $event,
            'user' => $user,
            'rsvp' => $rsvp,
            'checkedIn' => $checkedIn,
            'canRsvp' => $user && Gate::allows('rsvp', $event),
            'goingCount' => $event->rsvps()->where('status', 'going')->count(),
            // Personal QR for a going attendee: staff scan it to check them in.
            'passUrl' => $user && $rsvp?->status === 'going' && ! $event->hasEnded()
                ? URL::temporarySignedRoute('events.checkin.scan', $event->checkInClosesAt(), ['event' => $event, 'user' => $user])
                : null,
        ]);
    }

    public function manage(): View
    {
        Gate::authorize('events.manage');

        $events = Event::query()->latest('starts_at')->paginate(20);

        return view('announcements::events.manage', compact('events'));
    }

    public function create(): View
    {
        Gate::authorize('events.manage');

        return view('announcements::events.form', ['event' => new Event(['kind' => 'event'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('events.manage');

        $data = $this->withAgenda($this->validated($request));
        Event::query()->create([
            ...$data,
            'author_id' => $request->user()->id,
            'published_at' => $request->boolean('publish') ? now() : null,
        ]);

        return redirect()->route('events.manage')->with('status', __('Event saved.'));
    }

    public function edit(Event $event): View
    {
        Gate::authorize('events.manage');

        return view('announcements::events.form', compact('event'));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('events.manage');

        $event->fill($this->withAgenda($this->validated($request)));
        if ($request->boolean('publish') && ! $event->published_at) {
            $event->published_at = now();
        }
        if ($request->boolean('unpublish')) {
            $event->published_at = null;
        }
        $event->save();

        return redirect()->route('events.manage')->with('status', __('Event updated.'));
    }

    /**
     * Turn the agenda textarea into the stored JSON list.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withAgenda(array $data): array
    {
        // Only touch the agenda when the form sent it, so API-style updates keep it.
        if (array_key_exists('agenda', $data)) {
            $data['agenda'] = Event::parseAgenda($data['agenda']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'agenda' => ['nullable', 'string', 'max:5000'],
            'minutes' => ['nullable', 'string', 'max:50000'],
            'title' => ['required', 'string', 'max:180'],
            'kind' => ['required', 'in:event,webinar,meeting,agm'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string', 'max:20000'],
            'venue' => ['nullable', 'string', 'max:255'],
            'online_url' => ['nullable', 'url', 'max:500'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'audience' => ['required', 'in:public,members,parents'],
            'level' => ['required', 'in:national,state,chapter'],
        ]);
    }
}
