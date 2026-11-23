<?php

use App\Models\User;

it('signs in with phone number and password', function () {
    $user = User::factory()->create(['phone' => '+2348031234567']);

    $this->post('/login', ['login' => '0803 123 4567', 'password' => 'password'])
        ->assertRedirect('/home');

    $this->assertAuthenticatedAs($user);
});

it('signs in with email and password', function () {
    $user = User::factory()->create(['email' => 'bursar@example.com']);

    $this->post('/login', ['login' => 'Bursar@Example.com', 'password' => 'password'])
        ->assertRedirect('/home');

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    User::factory()->create(['phone' => '+2348031234567']);

    $this->post('/login', ['login' => '08031234567', 'password' => 'wrong-password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('never lets a phone-only account sign in with a password', function () {
    User::factory()->phoneOnly()->create(['phone' => '+2348031234567']);

    $this->post('/login', ['login' => '08031234567', 'password' => ''])
        ->assertSessionHasErrors();

    $this->assertGuest();
});

it('lets a phone-only account add a password without a current one', function () {
    $user = User::factory()->phoneOnly()->create();

    $this->actingAs($user)
        ->put('/user/password', ['password' => 'a-new-secret-1', 'password_confirmation' => 'a-new-secret-1'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->hasPassword())->toBeTrue();
});

it('requires the current password to change an existing one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/user/password', ['password' => 'a-new-secret-1', 'password_confirmation' => 'a-new-secret-1'])
        ->assertSessionHasErrorsIn('updatePassword', 'current_password');
});
