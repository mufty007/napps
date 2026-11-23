<?php

use Illuminate\Support\Facades\Route;
use Modules\Fees\Http\Controllers\FeeAgreementController;
use Modules\Fees\Http\Controllers\FeeInvoiceController;
use Modules\Fees\Http\Controllers\FeePaymentController;
use Modules\Fees\Http\Controllers\FeeStructureController;
use Modules\Fees\Http\Controllers\ParentInvoiceController;

// Access is decided by the policies (active role + school scope), not by route middleware.
Route::middleware('member')->prefix('fees')->name('fees.')->group(function () {
    // FEE-3: parent portal. Parents are personal-scope, so "parentInvoice" is bound by guardian, not school.
    Route::prefix('my')->name('parent.')->middleware('can:fees.pay')->group(function () {
        Route::get('/', [ParentInvoiceController::class, 'index'])->name('index');
        Route::get('/{parentInvoice}', [ParentInvoiceController::class, 'show'])->whereNumber('parentInvoice')->name('show');
        Route::post('/{parentInvoice}/pay', [FeePaymentController::class, 'start'])->whereNumber('parentInvoice')->middleware('throttle:10,1')->name('pay');
    });

    // FEE-4: agreements. Parents sign; school staff read.
    Route::prefix('agreements')->name('agreements.')->controller(FeeAgreementController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
        Route::get('/{agreement}', 'show')->whereNumber('agreement')->name('show');
    });

    // FEE-1: fee structures.
    Route::prefix('structures')->name('structures.')->controller(FeeStructureController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{structure}/edit', 'edit')->whereNumber('structure')->name('edit');
        Route::put('/{structure}', 'update')->whereNumber('structure')->name('update');
        Route::delete('/{structure}', 'destroy')->whereNumber('structure')->name('destroy');
    });

    // FEE-1/FEE-2: invoices and collections.
    Route::prefix('invoices')->name('invoices.')->controller(FeeInvoiceController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('/remind-overdue', 'remindOverdue')->middleware('throttle:6,1')->name('remind-overdue');
        Route::get('/{invoice}', 'show')->whereNumber('invoice')->name('show');
        Route::post('/{invoice}/issue', 'issue')->whereNumber('invoice')->name('issue');
        Route::post('/{invoice}/void', 'void')->whereNumber('invoice')->name('void');
        Route::post('/{invoice}/remind', 'remind')->whereNumber('invoice')->middleware('throttle:20,1')->name('remind');
        Route::post('/{invoice}/escalate', 'escalate')->whereNumber('invoice')->name('escalate');
        Route::post('/{invoice}/payments', [FeePaymentController::class, 'store'])->whereNumber('invoice')->middleware('throttle:30,1')->name('payments.store');
    });
});
