<?php

namespace App\Models;

use App\Enums\Role;
use App\Support\PhoneNumber;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'phone', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, LogsActivity, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function holdsRole(Role $role): bool
    {
        return $this->roleAssignments()
            ->whereHas('role', fn ($q) => $q->where('name', $role->value))
            ->exists();
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    /**
     * Fortify's sign-in field is "login" (phone or email); it also labels the
     * account in authenticator apps with it.
     *
     * @return Attribute<string, never>
     */
    protected function login(): Attribute
    {
        return Attribute::get(fn () => $this->email ?? PhoneNumber::format($this->phone));
    }

    /**
     * Route for the SMS notification channel.
     */
    public function routeNotificationForSms(): string
    {
        return $this->phone;
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('accounts')
            ->logOnly(['name', 'phone', 'email', 'locale'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
