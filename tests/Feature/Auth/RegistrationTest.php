<?php

use App\Models\User;

it('registers a new account after confirming the phone by SMS', function () {
    $this->get('/register')->assertOk();

    $this->post('/register', [
        'name' => 'Adaeze Obi',
        'phone' => '0812 345 6789',
        'email' => 'Adaeze@Example.com',
        'terms' => '1',
    ])->assertRedirect(route('register.verify'));

    expect(User::query()->count())->toBe(0);

    $code = $this->lastSmsCode('+2348123456789');

    $this->post('/register/verify', ['code' => $code])->assertRedirect(route('dashboard'));

    $user = User::query()->sole();
    expect($user->name)->toBe('Adaeze Obi')
        ->and($user->phone)->toBe('+2348123456789')
        ->and($user->email)->toBe('adaeze@example.com')
        ->and($user->phone_verified_at)->not->toBeNull()
        ->and($user->password)->toBeNull();

    $this->assertAuthenticatedAs($user);
});

it('refuses a phone number that already has an account', function () {
    User::factory()->create(['phone' => '+2348123456789']);

    $this->post('/register', ['name' => 'Someone', 'phone' => '08123456789', 'terms' => '1'])
        ->assertSessionHasErrors('phone_e164');

    expect($this->sms->sent)->toBeEmpty();
});

it('requires accepting the terms', function () {
    $this->post('/register', ['name' => 'Someone', 'phone' => '08123456789'])
        ->assertSessionHasErrors('terms');
});

it('does not create the account with a wrong code', function () {
    $this->post('/register', ['name' => 'Someone', 'phone' => '08123456789', 'terms' => '1']);

    $this->post('/register/verify', ['code' => '000000'])->assertSessionHasErrors('code');

    expect(User::query()->count())->toBe(0);
});

it('lands a new account without roles on the welcome screen', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Your account is ready');
});
