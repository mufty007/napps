<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Modules\Announcements\Models\Event;
use Modules\Announcements\Models\EventCheckin;
use Modules\Announcements\Models\EventRsvp;

function engagementEvent(array $attributes = []): Event
{
    return Event::query()->create([
        'title' => 'Chapter AGM',
        'slug' => 'chapter-agm',
        'kind' => 'agm',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(3),
        'audience' => 'public',
        'level' => 'national',
        'author_id' => User::factory()->create()->id,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
}

it('lets a signed-in member RSVP and change their answer, one row per person', function () {
    $event = engagementEvent();
    $user = $this->actingAsRole(Role::Proprietor, memberSchool(membershipOrg()['kanoChapter'])->id);

    $this->post(route('events.rsvp', $event), ['status' => 'going'])->assertRedirect(route('events.show', $event));
    expect(EventRsvp::query()->where('event_id', $event->id)->where('user_id', $user->id)->sole()->status)->toBe('going');

    $this->post(route('events.rsvp', $event), ['status' => 'maybe'])->assertRedirect();
    $this->post(route('events.rsvp', $event), ['status' => 'declined'])->assertRedirect();

    expect(EventRsvp::query()->count())->toBe(1)
        ->and(EventRsvp::query()->sole()->status)->toBe('declined');

    $this->get(route('events.show', $event))->assertOk()->assertSee('Will you be there?')->assertSee("Can't go");
});

it('rejects unknown RSVP answers and guests', function () {
    $event = engagementEvent();

    $this->post(route('events.rsvp', $event), ['status' => 'going'])->assertRedirect('/login');

    $this->actingAsRole(Role::Proprietor, memberSchool(membershipOrg()['kanoChapter'])->id);
    $this->post(route('events.rsvp', $event), ['status' => 'definitely'])->assertSessionHasErrors('status');
    $this->post(route('events.rsvp', $event))->assertSessionHasErrors('status');
    expect(EventRsvp::query()->count())->toBe(0);
});

it('only takes RSVPs for published events that are still to come', function () {
    $this->actingAsRole(Role::NationalExecutive);

    $draft = engagementEvent(['slug' => 'draft', 'published_at' => null]);
    $over = engagementEvent(['slug' => 'over', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHours(2)]);

    $this->post(route('events.rsvp', $draft), ['status' => 'going'])->assertForbidden();
    $this->post(route('events.rsvp', $over), ['status' => 'going'])->assertForbidden();
    expect(EventRsvp::query()->count())->toBe(0);
});

it('keeps members-only events away from parents', function () {
    $event = engagementEvent(['audience' => 'members']);

    $this->actingAsRole(Role::Parent);
    $this->post(route('events.rsvp', $event), ['status' => 'going'])->assertForbidden();

    $this->actingAsRole(Role::NationalExecutive);
    $this->post(route('events.rsvp', $event), ['status' => 'going'])->assertRedirect();
});

it('checks a member in through the signed venue QR', function () {
    $event = engagementEvent(['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(2)]);
    $user = $this->actingAsRole(Role::Proprietor, memberSchool(membershipOrg()['kanoChapter'])->id);

    $url = URL::temporarySignedRoute('events.checkin.self', now()->addHours(5), ['event' => $event]);

    $this->get($url)->assertOk()->assertSee("You're checked in");

    $checkin = EventCheckin::query()->where('event_id', $event->id)->where('user_id', $user->id)->sole();
    expect($checkin->checked_in_at)->not->toBeNull()
        // Turning up counts as RSVPing "going".
        ->and(EventRsvp::query()->where('user_id', $user->id)->sole()->status)->toBe('going');

    // Scanning again is harmless and keeps the first arrival time.
    $first = $checkin->checked_in_at;
    $this->travel(5)->minutes();
    $this->get($url)->assertOk()->assertSee("You're already checked in");
    expect(EventCheckin::query()->count())->toBe(1)
        ->and(EventCheckin::query()->sole()->checked_in_at->eq($first))->toBeTrue();
});

it('rejects forged, expired or out-of-window check-in links', function () {
    $event = engagementEvent(['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(2)]);
    $this->actingAsRole(Role::Proprietor, memberSchool(membershipOrg()['kanoChapter'])->id);

    $this->get(route('events.checkin.self', $event))->assertForbidden(); // unsigned

    $signed = URL::temporarySignedRoute('events.checkin.self', now()->addHour(), ['event' => $event]);
    $this->get($signed.'x')->assertForbidden(); // tampered

    $this->travel(2)->hours();
    $this->get($signed)->assertForbidden(); // expired

    expect(EventCheckin::query()->count())->toBe(0);
});

it('refuses check-in before the window opens', function () {
    $event = engagementEvent(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(2)]);
    $this->actingAsRole(Role::Proprietor, memberSchool(membershipOrg()['kanoChapter'])->id);

    $url = URL::temporarySignedRoute('events.checkin.self', now()->addDays(3), ['event' => $event]);

    $this->get($url)->assertForbidden()->assertSee('Check-in is closed');
    expect(EventCheckin::query()->count())->toBe(0);
});

it('lets organisers scan an attendee pass but nobody else', function () {
    $event = engagementEvent(['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(2)]);
    $attendee = User::factory()->create(['name' => 'Hauwa Garba']);

    $pass = URL::temporarySignedRoute('events.checkin.scan', now()->addHours(5), ['event' => $event, 'user' => $attendee]);

    $this->actingAsRole(Role::Proprietor, memberSchool(membershipOrg()['kanoChapter'])->id);
    $this->get($pass)->assertForbidden();
    expect(EventCheckin::query()->count())->toBe(0);

    $staff = $this->actingAsRole(Role::NationalExecutive);
    $this->get($pass)->assertOk()->assertSee('Hauwa Garba is checked in');

    $checkin = EventCheckin::query()->sole();
    expect($checkin->user_id)->toBe($attendee->id)->and($checkin->checked_in_by)->toBe($staff->id);
});

it('shows organisers the attendance list and venue QR, and ticks people off by hand', function () {
    $event = engagementEvent(['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(2)]);
    $going = User::factory()->create(['name' => 'Aisha Going']);
    $maybe = User::factory()->create(['name' => 'Musa Maybe']);
    EventRsvp::query()->create(['event_id' => $event->id, 'user_id' => $going->id, 'status' => 'going']);
    EventRsvp::query()->create(['event_id' => $event->id, 'user_id' => $maybe->id, 'status' => 'maybe']);

    $this->actingAsRole(Role::Proprietor, memberSchool(membershipOrg()['kanoChapter'])->id);
    $this->get(route('events.attendance', $event))->assertForbidden();

    $this->actingAsRole(Role::NationalExecutive);
    $this->get(route('events.attendance', $event))
        ->assertOk()->assertSee('Aisha Going')->assertSee('Musa Maybe')->assertSee('<svg', false);

    $this->post(route('events.attendance.checkin', [$event, $going]))->assertRedirect(route('events.attendance', $event));
    expect(EventCheckin::query()->where('user_id', $going->id)->exists())->toBeTrue();

    // Only people who RSVPed can be ticked off.
    $this->post(route('events.attendance.checkin', [$event, User::factory()->create()]))->assertNotFound();
});

it('shows going attendees their personal check-in pass', function () {
    $event = engagementEvent();
    $user = $this->actingAsRole(Role::Proprietor, memberSchool(membershipOrg()['kanoChapter'])->id);

    $this->get(route('events.show', $event))->assertDontSee('Your check-in pass');

    EventRsvp::query()->create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'going']);
    $this->get(route('events.show', $event))->assertSee('Your check-in pass')->assertSee('<svg', false);
});

it('saves an agenda and minutes on an event', function () {
    $this->actingAsRole(Role::NationalExecutive);

    $this->post(route('events.store'), [
        'title' => 'National forum',
        'kind' => 'meeting',
        'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'),
        'audience' => 'public',
        'level' => 'national',
        'agenda' => "09:00 | Registration\nKeynote\n\n10:30|Break",
        'publish' => '1',
    ])->assertRedirect(route('events.manage'));

    $event = Event::query()->where('title', 'National forum')->firstOrFail();
    expect($event->agendaItems())->toBe([
        ['time' => '09:00', 'title' => 'Registration'],
        ['time' => '', 'title' => 'Keynote'],
        ['time' => '10:30', 'title' => 'Break'],
    ]);

    $this->put(route('events.update', $event), [
        'title' => 'National forum',
        'kind' => 'meeting',
        'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
        'audience' => 'public',
        'level' => 'national',
        'agenda' => "09:00 | Registration\nKeynote\n10:30 | Break",
        'minutes' => 'Resolved to hold the next forum in Abuja.',
    ])->assertRedirect(route('events.manage'));

    // A save that does not send the agenda leaves it alone.
    $this->put(route('events.update', $event), [
        'title' => 'National forum (renamed)',
        'kind' => 'meeting',
        'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
        'audience' => 'public',
        'level' => 'national',
    ])->assertRedirect(route('events.manage'));

    expect($event->fresh()->agendaItems())->toHaveCount(3);

    // Agenda is public, minutes are for signed-in members.
    auth()->logout();
    $this->get(route('events.show', $event))->assertOk()->assertSee('Registration')->assertDontSee('Resolved to hold');

    $this->actingAsRole(Role::Proprietor, memberSchool(membershipOrg()['kanoChapter'])->id);
    $this->get(route('events.show', $event))->assertSee('Resolved to hold the next forum in Abuja.');
});

it('keeps existing public event pages working for guests', function () {
    $event = engagementEvent();

    $this->get(route('events.show', $event))->assertOk()->assertSee('Chapter AGM')->assertSee('Sign in to RSVP');
});
