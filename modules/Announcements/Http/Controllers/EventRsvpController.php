<?php

namespace Modules\Announcements\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Announcements\Models\Event;
use Modules\Announcements\Models\EventRsvp;

class EventRsvpController extends Controller
{
    /**
     * Set (or change) the signed-in person's answer. One row per person per event.
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('rsvp', $event);

        $status = $request->validate([
            'status' => ['required', Rule::in(EventRsvp::STATUSES)],
        ])['status'];

        EventRsvp::query()->updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $request->user()->id],
            ['status' => $status],
        );

        return redirect()->route('events.show', $event)->with('status', match ($status) {
            'going' => __("You're going. See you there."),
            'maybe' => __('Marked as maybe.'),
            default => __('Marked as not going.'),
        });
    }
}
