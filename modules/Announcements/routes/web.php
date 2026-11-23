<?php

use Illuminate\Support\Facades\Route;
use Modules\Announcements\Http\Controllers\DirectoryController;
use Modules\Announcements\Http\Controllers\ElectionController;
use Modules\Announcements\Http\Controllers\EventCheckinController;
use Modules\Announcements\Http\Controllers\EventController;
use Modules\Announcements\Http\Controllers\EventRsvpController;
use Modules\Announcements\Http\Controllers\JobBoardController;
use Modules\Announcements\Http\Controllers\NewsController;
use Modules\Announcements\Models\Event;
use Modules\Announcements\Models\JobListing;
use Modules\Announcements\Models\NewsPost;

Route::bind('news', fn (string $value) => NewsPost::query()
    ->where(ctype_digit($value) ? 'id' : 'slug', $value)
    ->firstOrFail());

Route::bind('event', fn (string $value) => Event::query()
    ->where(ctype_digit($value) ? 'id' : 'slug', $value)
    ->firstOrFail());

Route::bind('job', fn (string $value) => JobListing::query()
    ->where(ctype_digit($value) ? 'id' : 'slug', $value)
    ->firstOrFail());

// Public hubs
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{news}', [NewsController::class, 'show'])->name('news.show');

Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');

Route::get('/jobs', [JobBoardController::class, 'index'])->name('jobs.index');
Route::get('/jobs/{job}', [JobBoardController::class, 'show'])->name('jobs.show');

// What membership gives you (public: also a reason to join).
Route::view('/benefits', 'announcements::benefits')->name('benefits');

Route::middleware('member')->group(function () {
    // Event attendance: RSVP and QR check-in (the links are signed, see EventCheckinController).
    Route::post('/events/{event}/rsvp', [EventRsvpController::class, 'store'])->name('events.rsvp');
    Route::get('/events/{event}/check-in', [EventCheckinController::class, 'self'])
        ->middleware('signed')->name('events.checkin.self');
    Route::get('/events/{event}/check-in/{user}', [EventCheckinController::class, 'scan'])
        ->middleware('signed')->name('events.checkin.scan');

    // Member directory and elections.
    Route::get('/directory', [DirectoryController::class, 'index'])->name('directory.index');

    Route::prefix('elections')->name('elections.')->group(function () {
        Route::get('/', [ElectionController::class, 'index'])->name('index');
        Route::get('/create', [ElectionController::class, 'create'])->name('create');
        Route::post('/', [ElectionController::class, 'store'])->name('store');
        Route::get('/{election}', [ElectionController::class, 'show'])->name('show');
        Route::get('/{election}/edit', [ElectionController::class, 'edit'])->name('edit');
        Route::put('/{election}', [ElectionController::class, 'update'])->name('update');
        Route::delete('/{election}', [ElectionController::class, 'destroy'])->name('destroy');
        Route::post('/{election}/open', [ElectionController::class, 'open'])->name('open');
        Route::post('/{election}/close', [ElectionController::class, 'close'])->name('close');
        Route::post('/{election}/candidates', [ElectionController::class, 'storeCandidate'])->name('candidates.store');
        Route::delete('/{election}/candidates/{candidate}', [ElectionController::class, 'destroyCandidate'])->name('candidates.destroy');
        Route::post('/{election}/vote', [ElectionController::class, 'vote'])->name('vote');
    });
});

// Executive management
Route::middleware('member')->group(function () {
    Route::middleware('can:announcements.send')->prefix('manage/news')->name('news.')->group(function () {
        Route::get('/', [NewsController::class, 'manage'])->name('manage');
        Route::get('/create', [NewsController::class, 'create'])->name('create');
        Route::post('/', [NewsController::class, 'store'])->name('store');
        Route::get('/{news}/edit', [NewsController::class, 'edit'])->name('edit');
        Route::put('/{news}', [NewsController::class, 'update'])->name('update');
    });

    Route::middleware('can:events.manage')->prefix('manage/events')->name('events.')->group(function () {
        Route::get('/', [EventController::class, 'manage'])->name('manage');
        Route::get('/create', [EventController::class, 'create'])->name('create');
        Route::post('/', [EventController::class, 'store'])->name('store');
        Route::get('/{event}/edit', [EventController::class, 'edit'])->name('edit');
        Route::put('/{event}', [EventController::class, 'update'])->name('update');
        Route::get('/{event}/attendance', [EventCheckinController::class, 'attendance'])->name('attendance');
        Route::post('/{event}/attendance/{user}', [EventCheckinController::class, 'manual'])->name('attendance.checkin');
    });

    Route::middleware('can:jobs.manage')->prefix('manage/jobs')->name('jobs.')->group(function () {
        Route::get('/', [JobBoardController::class, 'manage'])->name('manage');
        Route::get('/create', [JobBoardController::class, 'create'])->name('create');
        Route::post('/', [JobBoardController::class, 'store'])->name('store');
        Route::get('/{job}/edit', [JobBoardController::class, 'edit'])->name('edit');
        Route::put('/{job}', [JobBoardController::class, 'update'])->name('update');
    });
});
