<?php

namespace Modules\Templates\Providers;

use App\Enums\Role;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Navigation\Navigation;
use App\Support\Navigation\NavItem;

/**
 * Versioned template library with legally-reviewed flag, fill-in fields, PDF export, starter set.
 */
class TemplatesServiceProvider extends ModuleServiceProvider
{
    protected string $module = 'Templates';

    protected function navigation(Navigation $navigation): void
    {
        // Every role that holds templates.use or templates.publish (see PermissionMatrix).
        $navigation->add(new NavItem(
            key: 'templates',
            label: 'Templates',
            route: 'templates.index',
            icon: 'file-text',
            roles: [
                Role::Secretariat,
                Role::NationalExecutive,
                Role::StateExecutive,
                Role::ChapterExecutive,
                Role::Proprietor,
                Role::Bursar,
                Role::LegalPanel,
            ],
            order: 50,
            active: 'templates.*',
            mobile: false,
        ));
    }
}
