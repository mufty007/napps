<?php

use App\Models\OtpCode;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->phoneOnly()->create(['phone' => '+2348031234567']);
});

it('shows the phone-first sign-in page', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Text me a code');
});

it('signs a user in with an SMS code', function () {
    $this->post('/login/otp', ['phone' => '0803 123 4567'])
        ->assertRedirect(route('login.otp.verify'));

    $code = $this->lastSmsCode('+2348031234567');
    expect($code)->not->toBeNull();

    $this->get(route('login.otp.verify'))->assertOk()->assertSee('+234 803 *** 4567');

    $this->post('/login/otp/verify', ['code' => $code])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->user);
    expect($this->user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects a wrong code and locks after too many attempts', function () {
    $this->post('/login/otp', ['phone' => '08031234567']);
    $code = $this->lastSmsCode('+2348031234567');
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, config('napps.otp.max_attempts')) as $attempt) {
        $this->post('/login/otp/verify', ['code' => $wrong])->assertSessionHasErrors('code');
    }

    // The right code no longer works once the attempts are used up.
    $this->post('/login/otp/verify', ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('does not accept an expired code', function () {
    $this->post('/login/otp', ['phone' => '08031234567']);
    $code = $this->lastSmsCode('+2348031234567');

    $this->travel(config('napps.otp.ttl_minutes') + 1)->minutes();

    $this->post('/login/otp/verify', ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('does not reveal whether a number is registered', function () {
    $this->post('/login/otp', ['phone' => '08099999999'])
        ->assertRedirect(route('login.otp.verify'));

    expect($this->sms->sent)->toBeEmpty()
        ->and(OtpCode::query()->count())->toBe(0);

    $this->post('/login/otp/verify', ['code' => '123456'])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('rate limits code requests per number', function () {
    foreach (range(1, config('napps.otp.send_limit')) as $i) {
        $this->post('/login/otp', ['phone' => '08031234567'])->assertSessionHasNoErrors();
    }

    $this->post('/login/otp', ['phone' => '08031234567'])->assertSessionHasErrors('phone');
});

it('validates the phone number', function () {
    $this->post('/login/otp', ['phone' => '12345'])->assertSessionHasErrors('phone');
});

it('hands users with two-factor on to the 2FA challenge', function () {
    $user = User::factory()->withTwoFactor()->create(['phone' => '+2348051112222']);

    $this->post('/login/otp', ['phone' => '08051112222']);
    $code = $this->lastSmsCode('+2348051112222');

    $this->post('/login/otp/verify', ['code' => $code])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id);

    $this->assertGuest();
});

it('stores codes hashed and single-use', function () {
    $this->post('/login/otp', ['phone' => '08031234567']);
    $code = $this->lastSmsCode('+2348031234567');

    expect(OtpCode::query()->first()->code_hash)->not->toBe($code);

    $this->post('/login/otp/verify', ['code' => $code]);
    $this->post('/logout');

    $this->withSession(['otp' => ['phone' => '+2348031234567', 'purpose' => 'login']])
        ->post('/login/otp/verify', ['code' => $code])
        ->assertSessionHasErrors('code');
});
