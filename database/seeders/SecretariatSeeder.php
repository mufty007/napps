<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Bootstrap secretariat admin from SECRETARIAT_NAME / SECRETARIAT_PHONE /
 * SECRETARIAT_EMAIL. They sign in by SMS code and must set up 2FA first.
 */
class SecretariatSeeder extends Seeder
{
    public function run(): void
    {
        $phone = PhoneNumber::normalize(config('napps.secretariat.phone'))
            ?? throw new RuntimeException('SECRETARIAT_PHONE is not a valid Nigerian phone number.');

        $user = User::query()->firstOrNew(['phone' => $phone]);
        $user->fill([
            'name' => $user->name ?? config('napps.secretariat.name'),
            'email' => $user->email ?? config('napps.secretariat.email'),
        ]);
        $user->phone_verified_at ??= now();
        $user->save();

        RoleAssignment::grant($user, Role::Secretariat, ScopeLevel::National);
    }
}
