<?php

namespace Modules\Community\Providers;

use App\Enums\Role;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavItem;

/**
 * Members-only feed, forums, groups, chat (Reverb), polls and formal votes,
 * profiles, moderation. Never reads fees/student tables. Stories: COM-1..5.
 */
class CommunityServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Community';

    protected function navigation(Navigation $navigation): void
    {
        $navigation->add(new NavItem(
            key: 'community',
            label: 'Community',
            route: 'community.index',
            icon: 'messages-square',
            roles: array_values(array_filter(Role::cases(), fn (Role $r) => $r->hasCommunityAccess())),
            order: 90,
            active: 'community.*',
            permission: 'community.access',
        ));
    }
}
