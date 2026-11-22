<?php

namespace Tests;

use App\Contracts\SmsGateway;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Sms\LogSmsGateway;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected LogSmsGateway $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->sms = new LogSmsGateway;
        $this->app->instance(SmsGateway::class, $this->sms);
    }

    protected function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Create (or reuse) a user holding the given role and sign them in.
     */
    protected function actingAsRole(Role $role, ?int $scopeId = null, ?User $user = null, ?ScopeLevel $level = null): User
    {
        $this->seedRoles();

        $user ??= User::factory()->withTwoFactor()->create();
        RoleAssignment::grant($user, $role, $level ?? $role->allowedLevels()[0], $scopeId);

        $this->actingAs($user);

        return $user;
    }

    /**
     * The last SMS code sent to a phone number.
     */
    protected function lastSmsCode(string $phone): ?string
    {
        $message = collect($this->sms->sent)->where('to', $phone)->last();

        return $message && preg_match('/\b(\d{6})\b/', $message['message'], $m) ? $m[1] : null;
    }
}
