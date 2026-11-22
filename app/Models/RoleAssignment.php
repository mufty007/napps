<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\ScopeLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * One role held by one user at one place in the org chain.
 *
 * @property ScopeLevel $scope_type
 */
#[Fillable(['user_id', 'role_id', 'scope_type', 'scope_id', 'granted_by'])]
class RoleAssignment extends Model
{
    use LogsActivity;

    private State|Chapter|School|false|null $resolvedScopeModel = false;

    protected function casts(): array
    {
        return [
            'scope_type' => ScopeLevel::class,
            'scope_id' => 'integer',
        ];
    }

    /**
     * Grant a role at a place in the org chain (idempotent).
     */
    public static function grant(
        User $user,
        Role $role,
        ScopeLevel $level,
        ?int $scopeId = null,
        ?User $grantedBy = null,
    ): self {
        if (! in_array($level, $role->allowedLevels(), true)) {
            throw new InvalidArgumentException("Role [{$role->value}] cannot be granted at the [{$level->value}] level.");
        }

        if ($level->requiresScopeId() === ($scopeId === null)) {
            throw new InvalidArgumentException("The [{$level->value}] level ".($scopeId === null ? 'needs' : 'does not take').' a scope id.');
        }

        return static::query()->firstOrCreate([
            'user_id' => $user->getKey(),
            'role_id' => RoleModel::findByName($role->value, 'web')->getKey(),
            'scope_type' => $level,
            'scope_id' => $scopeId,
        ], [
            'granted_by' => $grantedBy?->getKey(),
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<RoleModel, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(RoleModel::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function roleKey(): ?Role
    {
        return Role::tryFrom((string) $this->role?->name);
    }

    /**
     * The org unit this assignment is anchored to. Loaded without global
     * scopes: the active-scope resolver depends on it.
     */
    public function scopeModel(): State|Chapter|School|null
    {
        if ($this->resolvedScopeModel !== false) {
            return $this->resolvedScopeModel;
        }

        $model = match ($this->scope_type) {
            ScopeLevel::State => State::query()->find($this->scope_id),
            ScopeLevel::Chapter => Chapter::query()->find($this->scope_id),
            ScopeLevel::School => School::withoutGlobalScopes()->find($this->scope_id),
            default => null,
        };

        return $this->resolvedScopeModel = $model;
    }

    /**
     * Human label for the role switcher, e.g. "Proprietor · Bright Future Academy".
     */
    public function label(): string
    {
        $role = $this->roleKey()?->label() ?? (string) $this->role?->name;

        $place = match ($this->scope_type) {
            ScopeLevel::National => __('National'),
            ScopeLevel::Personal => null,
            default => $this->scopeModel()?->name,
        };

        return $place ? "{$role} · {$place}" : $role;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('roles')->logFillable()->logOnlyDirty();
    }
}
