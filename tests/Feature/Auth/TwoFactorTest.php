<?php

use App\Enums\Role;
use App\Models\Chapter;
use App\Models\School;
use App\Models\User;

it('sends executives without 2FA to their security settings', function () {
    $chapter = Chapter::factory()->create();
    $user = User::factory()->create();

    $this->actingAsRole(Role::ChapterExecutive, $chapter->id, $user);

    $this->get('/dashboard')
        ->assertRedirect(route('account.show'))
        ->assertSessionHas('warning');

    $this->get(route('account.show'))->assertOk()->assertSee('Two-factor authentication required');
});

it('sends bursars without 2FA to their security settings', function () {
    $school = School::factory()->create();

    $this->actingAsRole(Role::Bursar, $school->id, User::factory()->create());

    $this->get('/dashboard')->assertRedirect(route('account.show'));
});

it('lets executives with 2FA through', function () {
    $chapter = Chapter::factory()->create();

    $this->actingAsRole(Role::ChapterExecutive, $chapter->id, User::factory()->withTwoFactor()->create());

    $this->get('/dashboard')->assertOk();
});

it('does not require 2FA from proprietors or parents', function (Role $role) {
    $scopeId = $role === Role::Proprietor ? School::factory()->create()->id : null;

    $this->actingAsRole($role, $scopeId, User::factory()->create());

    $this->get('/dashboard')->assertOk();
})->with([Role::Proprietor, Role::Parent]);

it('always enforces 2FA in production even if the flag is off', function () {
    $this->actingAsRole(Role::NationalExecutive, user: User::factory()->create());

    config(['napps.two_factor.enforce' => false]);
    $this->app->detectEnvironment(fn () => 'production');

    $this->get('/dashboard')->assertRedirect(route('account.show'));
});

it('asks phone-only users to confirm by SMS code before enabling 2FA', function () {
    $user = User::factory()->phoneOnly()->create();

    $this->actingAs($user)
        ->post('/user/two-factor-authentication')
        ->assertRedirect(route('password.confirm'));

    $this->get(route('password.confirm'))->assertOk()->assertSee('Text a code to');

    $this->post(route('identity.otp.send'))->assertSessionHas('otp_sent');
    $code = $this->lastSmsCode($user->phone);

    $this->post(route('identity.otp.verify'), ['code' => $code])->assertSessionHas('auth.password_confirmed_at');

    $this->post('/user/two-factor-authentication')->assertSessionHasNoErrors();
    expect($user->fresh()->two_factor_secret)->not->toBeNull();

    $this->get(route('account.show'))->assertOk()->assertSee('Scan this QR code');
});
