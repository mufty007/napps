<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['member', 'can:community.access'])
    ->prefix('community')
    ->name('community.')
    ->group(function () {
        Route::view('/', 'community::hub')->name('index');
    });
