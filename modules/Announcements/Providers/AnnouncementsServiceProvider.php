<?php

namespace Modules\Announcements\Providers;

use App\Enums\Role;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavItem;
use Illuminate\Support\Facades\Gate;
use Modules\Announcements\Models\Election;
use Modules\Announcements\Models\Event;
use Modules\Announcements\Policies\ElectionPolicy;
use Modules\Announcements\Policies\EventPolicy;

/**
 * News & updates, events/webinars, job board, plus (later) circulars,
 * RSVP/QR check-in, event agenda and minutes, elections, directory and benefits. Story: ANN-1.
 */
class AnnouncementsServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Announcements';

    private const EXECUTIVES = [Role::Secretariat, Role::NationalExecutive, Role::StateExecutive, Role::ChapterExecutive];

    protected function navigation(Navigation $navigation): void
    {
        $navigation
            ->add(new NavItem(
                key: 'news.manage',
                label: 'News',
                route: 'news.manage',
                icon: 'newspaper',
                roles: self::EXECUTIVES,
                order: 55,
                active: 'news.*',
                permission: 'announcements.send',
                mobile: false,
            ))
            ->add(new NavItem(
                key: 'events.manage',
                label: 'Events',
                route: 'events.manage',
                icon: 'calendar-days',
                roles: self::EXECUTIVES,
                order: 56,
                active: 'events.*',
                permission: 'events.manage',
                mobile: false,
            ))
            ->add(new NavItem(
                key: 'jobs.manage',
                label: 'Jobs',
                route: 'jobs.manage',
                icon: 'briefcase',
                roles: self::EXECUTIVES,
                order: 57,
                active: 'jobs.*',
                permission: 'jobs.manage',
                mobile: false,
            ))
            ->add(new NavItem(
                key: 'events.elections',
                label: 'Elections',
                route: 'elections.index',
                icon: 'vote',
                roles: self::EXECUTIVES,
                order: 59,
                active: 'elections.*',
                permission: 'events.manage',
                mobile: false,
            ))
            ->add(new NavItem(
                key: 'events.ballots',
                label: 'Elections',
                route: 'elections.index',
                icon: 'vote',
                roles: [Role::Proprietor],
                order: 59,
                active: 'elections.*',
                permission: 'elections.vote',
                mobile: false,
            ))
            ->add(new NavItem(
                key: 'announcements.directory',
                label: 'Member directory',
                route: 'directory.index',
                icon: 'contact',
                roles: [...self::EXECUTIVES, Role::Proprietor],
                order: 60,
                active: 'directory.*',
                permission: 'directory.view',
                mobile: false,
            ))
            ->add(new NavItem(
                key: 'announcements.benefits',
                label: 'Member benefits',
                route: 'benefits',
                icon: 'gift',
                roles: [...self::EXECUTIVES, Role::Proprietor, Role::Bursar, Role::Staff],
                order: 61,
                active: 'benefits',
                mobile: false,
            ));
    }

    protected function bootModule(): void
    {
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Election::class, ElectionPolicy::class);
    }
}
