<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\ConfirmIdentityController;
use App\Http\Controllers\Auth\OtpLoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ContextController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shared kernel routes
|--------------------------------------------------------------------------
| Module routes live in modules/{Name}/routes/web.php. Fortify registers
| /login (view + password sign-in), /logout, /two-factor-challenge,
| /forgot-password, /user/* (profile, password, 2FA, confirm-password).
*/

Route::view('/', 'public.home')->name('home');

Route::middleware('guest')->group(function () {
    Route::post('/login/otp', [OtpLoginController::class, 'send'])->name('login.otp.send');
    Route::get('/login/otp', [OtpLoginController::class, 'show'])->name('login.otp.verify');
    Route::post('/login/otp/verify', [OtpLoginController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('login.otp.verify.store');
    Route::post('/login/otp/resend', [OtpLoginController::class, 'resend'])->name('login.otp.resend');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
    Route::get('/register/verify', [RegisterController::class, 'show'])->name('register.verify');
    Route::post('/register/verify', [RegisterController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('register.verify.store');
    Route::post('/register/resend', [RegisterController::class, 'resend'])->name('register.resend');
});

Route::middleware('auth')->group(function () {
    // Reachable before 2FA is set up, so required-2FA roles can turn it on.
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::post('/context', [ContextController::class, 'update'])->name('context.update');

    Route::post('/user/confirm-otp', [ConfirmIdentityController::class, 'send'])->name('identity.otp.send');
    Route::post('/user/confirm-otp/verify', [ConfirmIdentityController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('identity.otp.verify');
});

Route::middleware('member')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});
