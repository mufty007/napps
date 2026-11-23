<?php

namespace App\Support\Navigation;

use App\Enums\Role;
use App\Support\Scope\ActiveContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Registry of main-screen links. Modules add their screens; the app shell
 * shows the ones that match the active role and whose routes exist, grouped
 * into sidebar sections.
 */
class Navigation
{
    /**
     * Default section per key prefix (the part before the first dot), so
     * modules get sensible grouping without naming a section themselves.
     *
     * @var array<string, array{0: string|null, 1: int}> prefix => [label, order]
     */
    private const SECTIONS = [
        'home' => [null, 0],
        'dashboard' => [null, 0],
        'membership' => ['Membership', 10],
        'fees' => ['School', 20],
        'records' => ['School', 20],
        'leases' => ['School', 20],
        'claims' => ['Association', 30],
        'news' => ['Association', 30],
        'events' => ['Association', 30],
        'jobs' => ['Association', 30],
        'announcements' => ['Association', 30],
        'surveys' => ['Association', 30],
        'templates' => ['Association', 30],
        'community' => ['Community', 40],
        'platform' => ['Administration', 50],
    ];

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
            ->sortBy([
                fn (NavItem $a, NavItem $b) => $this->sectionOrder($a) <=> $this->sectionOrder($b),
                fn (NavItem $a, NavItem $b) => $a->order <=> $b->order,
            ])
            ->values();
    }

    /**
     * Items grouped by section heading, in display order. The first group
     * (Home) has a null heading.
     *
     * @param  Collection<int, NavItem>  $items
     * @return Collection<int, array{label: string|null, items: Collection<int, NavItem>}>
     */
    public function sections(Collection $items): Collection
    {
        return $items
            ->groupBy(fn (NavItem $item) => (string) $this->sectionLabel($item))
            ->map(fn (Collection $group, string $label) => ['label' => $label === '' ? null : $label, 'items' => $group->values()])
            ->values();
    }

    public function sectionLabel(NavItem $item): ?string
    {
        if ($item->section !== null) {
            return $item->section;
        }

        $prefix = $this->prefix($item);

        return array_key_exists($prefix, self::SECTIONS) ? self::SECTIONS[$prefix][0] : 'More';
    }

    private function sectionOrder(NavItem $item): int
    {
        if ($item->section !== null) {
            $match = collect(self::SECTIONS)->first(fn (array $s) => $s[0] === $item->section);

            return $match[1] ?? 60;
        }

        return self::SECTIONS[$this->prefix($item)][1] ?? 60;
    }

    private function prefix(NavItem $item): string
    {
        return strtok($item->key, '.') ?: $item->key;
    }
}
