<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+234'.fake()->unique()->numerify('80########'),
            'phone_verified_at' => now(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'locale' => 'en',
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Phone-only account (the common case for parents and proprietors).
     */
    public function phoneOnly(): static
    {
        return $this->state(fn () => [
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1', 'recovery-code-2'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
