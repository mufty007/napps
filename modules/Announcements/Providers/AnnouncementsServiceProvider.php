<?php

namespace Modules\Announcements\Providers;

use App\Support\Modules\ModuleServiceProvider;

/**
 * Announcements with channels and read receipts, circulars archive, events/RSVP/QR check-in, elections, directory, benefits. Story: ANN-1.
 */
class AnnouncementsServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Announcements';
}
