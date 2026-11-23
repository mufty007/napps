<?php

namespace Modules\Announcements\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Support\Scope\ActiveContext;
use Modules\Announcements\Models\Event;

/**
 * Attendance rules for signed-in people. Publishing and running events stays
 * with the "events.manage" permission.
 */
class EventPolicy
{
    public function __construct(private readonly ActiveContext $context) {}

    /** RSVP to a published event that has not finished. */
    public function rsvp(User $user, Event $event): bool
    {
        return $event->isPublished() && ! $event->hasEnded() && $this->audienceAllows($event);
    }

    /** Check in (yourself, via the venue QR) inside the check-in window. */
    public function checkIn(User $user, Event $event): bool
    {
        return $event->isPublished() && $event->checkInIsOpen() && $this->audienceAllows($event);
    }

    private function audienceAllows(Event $event): bool
    {
        return match ($event->audience) {
            'members' => $this->context->role() !== null && $this->context->role() !== Role::Parent,
            default => true,
        };
    }
}
