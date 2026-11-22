<?php

namespace App\Support\Navigation;

use App\Enums\Role;
use App\Support\Scope\ActiveContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Registry of main-screen links. Modules add their screens; the app shell
 * shows the ones that match the active role and whose routes exist.
 */
class Navigation
{
    /** @var array<string, NavItem> */
    private array $items = [];

    public function add(NavItem $item): static
    {
        $this->items[$item->key] = $item;

        return $this;
    }

    /**
     * @return Collection<int, NavItem>
     */
    public function for(?Role $role, ?ActiveContext $context = null): Collection
    {
        if (! $role) {
            return collect($this->items)->only('dashboard')->values();
        }

        return collect($this->items)
            ->filter(fn (NavItem $item) => in_array($role, $item->roles, true))
            ->filter(fn (NavItem $item) => Route::has($item->route))
            ->filter(fn (NavItem $item) => $item->permission === null || $context?->allows($item->permission))
            ->sortBy('order')
            ->values();
    }
}
