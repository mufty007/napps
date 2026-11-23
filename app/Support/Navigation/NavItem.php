<?php

namespace App\Support\Navigation;

use App\Enums\Role;

final readonly class NavItem
{
    /**
     * @param  list<Role>  $roles  Roles that see this item.
     * @param  string  $label  Untranslated label; rendered through __().
     * @param  string  $icon  Lucide icon name (without prefix).
     * @param  string|null  $active  Route-name pattern that marks the item active (defaults to "{route}*").
     * @param  bool  $mobile  Eligible for the phone bottom bar.
     * @param  string|null  $section  Sidebar section heading (untranslated); derived from the key when null.
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $route,
        public string $icon,
        public array $roles,
        public int $order = 50,
        public ?string $active = null,
        public bool $mobile = true,
        public ?string $permission = null,
        public ?string $section = null,
    ) {}

    /**
     * Route-name patterns that mark this item active ("a.*|b.*" allows several).
     *
     * @return list<string>
     */
    public function patterns(): array
    {
        return explode('|', $this->activePattern());
    }

    public function activePattern(): string
    {
        return $this->active ?? preg_replace('/\.index$/', '', $this->route).'*';
    }
}
