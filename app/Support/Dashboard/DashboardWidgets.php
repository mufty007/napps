<?php

namespace App\Support\Dashboard;

use App\Support\Scope\ActiveContext;

/**
 * Slots on the role home dashboards. Modules add Blade views (and feed them
 * with their own view composers); the kernel dashboard renders whatever is
 * registered for the active shell, so it never reaches into module tables.
 *
 * Shells: "secretariat", "executive", "school", "parent", "legal", and
 * "none" for signed-in people who hold no role yet.
 */
class DashboardWidgets
{
    /** @var list<array{shells: list<string>, view: string, order: int, permission: ?string}> */
    private array $widgets = [];

    /**
     * @param  string|list<string>  $shells
     */
    public function add(string|array $shells, string $view, int $order = 50, ?string $permission = null): static
    {
        $this->widgets[] = [
            'shells' => (array) $shells,
            'view' => $view,
            'order' => $order,
            'permission' => $permission,
        ];

        return $this;
    }

    /**
     * View names for a shell, in display order.
     *
     * @return list<string>
     */
    public function for(string $shell, ?ActiveContext $context = null): array
    {
        return collect($this->widgets)
            ->filter(fn (array $w) => in_array($shell, $w['shells'], true))
            ->filter(fn (array $w) => $w['permission'] === null || $context?->allows($w['permission']))
            ->sortBy('order')
            ->pluck('view')
            ->values()
            ->all();
    }
}
