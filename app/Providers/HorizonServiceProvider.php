<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Only secretariat staff can open the queue dashboard outside local.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null) {
            return $user?->holdsRole(Role::Secretariat) ?? false;
        });
    }
}
