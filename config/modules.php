<?php

/*
|--------------------------------------------------------------------------
| Domain modules
|--------------------------------------------------------------------------
| Each module lives in modules/{Name} with its own service provider, routes,
| views (namespace = lower-case name, e.g. "fees::"), migrations and policies.
| Modules may depend on the shared kernel in app/, never on each other's
| internals; cross-module calls go through events or small contracts.
*/

return [

    'path' => base_path('modules'),

    'enabled' => [
        'Membership',
        'Fees',
        'Claims',
        'Records',
        'Leases',
        'Surveys',
        'Templates',
        'Announcements',
        'Community',
        'Platform',
    ],

];
