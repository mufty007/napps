<?php

namespace App\Support\Scope;

use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The signed-in user's *active* role and where it sits in the org chain.
 *
 * Every scoped query (schools, and any module model that belongs to a school)
 * is narrowed through this object. MySQL has no row-level security, so this
 * is the single place that decides what a person may see.
 *
 * Rules:
 *  - No authenticated user (console, queue, public pages) = system context, unrestricted.
 *    Jobs and guest-facing controllers must therefore filter explicitly.
 *  - Authenticated user with no role, or a personal role = sees no org data.
 */
class ActiveContext
{
    public const SESSION_KEY = 'napps.active_assignment';

    private bool $resolved = false;

    private ?string $resolvedKey = null;

    private ?RoleAssignment $assignment = null;

    /** @var Collection<int, RoleAssignment> */
    private Collection $assignments;

    /** @var array<string, true> */
    private array $permissions = [];

    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Application $app,
    ) {
        $this->assignments = new Collection;
    }

    public function user(): ?User
    {
        $user = $this->auth->guard()->user();

        return $user instanceof User ? $user : null;
    }

    public function isSystem(): bool
    {
        return $this->user() === null;
    }

    public function assignment(): ?RoleAssignment
    {
        $this->resolve();

        return $this->assignment;
    }

    /**
     * @return Collection<int, RoleAssignment>
     */
    public function assignments(): Collection
    {
        $this->resolve();

        return $this->assignments;
    }

    public function role(): ?Role
    {
        return $this->assignment()?->roleKey();
    }

    public function level(): ?ScopeLevel
    {
        return $this->assignment()?->scope_type;
    }

    public function is(Role ...$roles): bool
    {
        return in_array($this->role(), $roles, true);
    }

    public function allows(string $permission): bool
    {
        $this->resolve();

        return isset($this->permissions[$permission]);
    }

    public function stateId(): ?int
    {
        $assignment = $this->assignment();

        return match ($assignment?->scope_type) {
            ScopeLevel::State => $assignment->scope_id,
            ScopeLevel::Chapter, ScopeLevel::School => $assignment->scopeModel()?->state_id,
            default => null,
        };
    }

    public function chapterId(): ?int
    {
        $assignment = $this->assignment();

        return match ($assignment?->scope_type) {
            ScopeLevel::Chapter => $assignment->scope_id,
            ScopeLevel::School => $assignment->scopeModel()?->chapter_id,
            default => null,
        };
    }

    public function schoolId(): ?int
    {
        $assignment = $this->assignment();

        return $assignment?->scope_type === ScopeLevel::School ? $assignment->scope_id : null;
    }

    /**
     * Whether the active role sits exactly at this place in the org chain
     * (e.g. it is the chapter that set a levy, not merely one that can see it).
     */
    public function isAt(ScopeLevel $level, ?int $scopeId = null): bool
    {
        $assignment = $this->assignment();

        if ($assignment === null || $assignment->scope_type !== $level) {
            return false;
        }

        return ! $level->requiresScopeId() || $assignment->scope_id === $scopeId;
    }

    /**
     * Make the given assignment the active one for this session.
     */
    public function switchTo(RoleAssignment $assignment): void
    {
        $request = $this->app->make('request');

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $assignment->getKey());
        }

        $this->forget();
    }

    public function forget(): void
    {
        $this->resolved = false;
        $this->resolvedKey = null;
        $this->assignment = null;
        $this->assignments = new Collection;
        $this->permissions = [];
    }

    /**
     * Narrow a query on the schools table to what the active role may see.
     * Only adds where-clauses, so any builder variance is safe.
     *
     * @param  Builder<covariant Model>  $query
     */
    public function constrainSchools(Builder $query): void
    {
        if ($this->isSystem()) {
            return;
        }

        $assignment = $this->assignment();
        $id = $assignment?->scope_id;

        match ($assignment?->scope_type) {
            ScopeLevel::National => null,
            ScopeLevel::State => $query->where($query->qualifyColumn('state_id'), $id),
            ScopeLevel::Chapter => $query->where($query->qualifyColumn('chapter_id'), $id),
            ScopeLevel::School => $query->where($query->qualifyColumn('id'), $id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Narrow any query whose rows carry a school id column.
     *
     * @param  Builder<covariant Model>  $query
     */
    public function constrainBySchoolColumn(Builder $query, string $column = 'school_id'): void
    {
        if ($this->isSystem()) {
            return;
        }

        $assignment = $this->assignment();
        $column = $query->qualifyColumn($column);
        $id = $assignment?->scope_id;

        match ($assignment?->scope_type) {
            ScopeLevel::National => null,
            ScopeLevel::State => $query->whereIn($column, School::withoutGlobalScopes()->select('id')->where('state_id', $id)),
            ScopeLevel::Chapter => $query->whereIn($column, School::withoutGlobalScopes()->select('id')->where('chapter_id', $id)),
            ScopeLevel::School => $query->where($column, $id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function resolve(): void
    {
        $user = $this->user();
        $selected = $this->selectedAssignmentId();
        $key = $user ? $user->getKey().'|'.($selected ?? '-') : null;

        if ($this->resolved && $this->resolvedKey === $key) {
            return;
        }

        $this->forget();
        $this->resolved = true;
        $this->resolvedKey = $key;

        if (! $user) {
            return;
        }

        $this->assignments = RoleAssignment::query()
            ->with('role.permissions')
            ->where('user_id', $user->getKey())
            ->get()
            ->sortBy(fn (RoleAssignment $a) => [$a->roleKey()?->priority() ?? 999, $a->getKey()])
            ->values();

        $this->assignment = $this->assignments->firstWhere('id', $selected)
            ?? $this->assignments->first();

        $this->permissions = $this->assignment
            ? array_fill_keys($this->assignment->role->permissions->pluck('name')->all(), true)
            : [];
    }

    private function selectedAssignmentId(): ?int
    {
        if (! $this->app->bound('request')) {
            return null;
        }

        $request = $this->app->make('request');

        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }
}
