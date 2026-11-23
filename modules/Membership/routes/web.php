<?php

use Illuminate\Support\Facades\Route;
use Modules\Membership\Http\Controllers\ApplicationReviewController;
use Modules\Membership\Http\Controllers\ApplyController;
use Modules\Membership\Http\Controllers\ChapterParentController;
use Modules\Membership\Http\Controllers\DocumentController;
use Modules\Membership\Http\Controllers\DuesChargeController;
use Modules\Membership\Http\Controllers\DuesController;
use Modules\Membership\Http\Controllers\SchoolRegistryController;
use Modules\Membership\Http\Controllers\VerifyController;

// Public: parents check a school's NAPPS standing; certificate QR codes land here.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/verify', [VerifyController::class, 'index'])->name('verify.index');
    Route::get('/verify/{number}', [VerifyController::class, 'show'])->name('verify.show');
});

// Signed document links (from screens, SMS and email).
Route::middleware('signed')->prefix('membership')->name('membership.')->group(function () {
    Route::get('/certificates/{school}', [DocumentController::class, 'certificate'])->whereNumber('school')->name('certificate');
    Route::get('/receipts/{payment}', [DocumentController::class, 'receipt'])->whereNumber('payment')->name('receipt');
});

Route::middleware('member')->prefix('membership')->name('membership.')->group(function () {
    // MEM-1: anyone signed in can apply for a school.
    Route::get('/apply', [ApplyController::class, 'create'])->name('apply');
    Route::post('/apply', [ApplyController::class, 'store'])->middleware('throttle:10,60')->name('apply.store');
    Route::get('/my-applications/{myApplication}', [ApplyController::class, 'show'])->name('apply.show');
    Route::get('/my-applications/{myApplication}/documents/{collection}', [ApplyController::class, 'document'])->name('apply.document');

    // MEM-3: proprietor's membership & dues for the active school. The index
    // authorizes in the controller, after an SMS link has switched role.
    Route::get('/dues', [DuesController::class, 'index'])->name('dues.index');
    Route::post('/dues/pay', [DuesController::class, 'pay'])->middleware(['can:membership.dues.pay', 'throttle:10,1'])->name('dues.pay');

    // Parents are enrolled by chapter executives only (no public parent signup).
    Route::middleware('can:parents.manage')->prefix('parents')->name('parents.')->group(function () {
        Route::get('/', [ChapterParentController::class, 'index'])->name('index');
        Route::get('/create', [ChapterParentController::class, 'create'])->name('create');
        Route::post('/', [ChapterParentController::class, 'store'])->middleware('throttle:20,1')->name('store');
    });

    // MEM-2: verification (chapter) and approval (state).
    Route::get('/applications', [ApplicationReviewController::class, 'index'])->name('applications.index');
    Route::get('/applications/{application}', [ApplicationReviewController::class, 'show'])->name('applications.show');
    Route::post('/applications/{application}/verify', [ApplicationReviewController::class, 'verify'])->name('applications.verify');
    Route::post('/applications/{application}/approve', [ApplicationReviewController::class, 'approve'])->name('applications.approve');
    Route::post('/applications/{application}/reject', [ApplicationReviewController::class, 'reject'])->name('applications.reject');
    Route::get('/applications/{application}/documents/{collection}', [ApplicationReviewController::class, 'document'])->name('applications.document');

    // Registry and compliance for executives.
    Route::middleware('can:org.view')->group(function () {
        Route::get('/schools', [SchoolRegistryController::class, 'index'])->name('schools.index');
        Route::get('/schools/{school}', [SchoolRegistryController::class, 'show'])->name('schools.show');
        Route::post('/schools/{school}/suspend', [SchoolRegistryController::class, 'suspend'])->middleware('can:membership.schools.suspend')->name('schools.suspend');
        Route::post('/schools/{school}/reinstate', [SchoolRegistryController::class, 'reinstate'])->middleware('can:membership.schools.suspend')->name('schools.reinstate');
        Route::post('/schools/{school}/payments', [SchoolRegistryController::class, 'recordPayment'])->name('schools.payments.store');
    });

    // Dues and levies set by each level.
    Route::prefix('dues/charges')->name('charges.')->controller(DuesChargeController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{charge}', 'show')->name('show');
        Route::get('/{charge}/edit', 'edit')->name('edit');
        Route::put('/{charge}', 'update')->name('update');
        Route::post('/{charge}/issue', 'issue')->name('issue');
        Route::post('/{charge}/cancel', 'cancel')->name('cancel');
    });
});
