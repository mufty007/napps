<?php

use Illuminate\Support\Facades\Route;
use Modules\Leases\Http\Controllers\LeaseController;
use Modules\Leases\Http\Controllers\LeaseDisputeController;
use Modules\Leases\Http\Controllers\LeaseDocumentController;
use Modules\Leases\Http\Controllers\RentPaymentController;

// Access is decided by LeasePolicy / LeaseDisputePolicy (active role + scope), not by route middleware.
Route::middleware('member')->prefix('leases')->name('leases.')->group(function () {
    Route::get('/', [LeaseController::class, 'index'])->name('index');
    Route::get('/create', [LeaseController::class, 'create'])->name('create');
    Route::post('/', [LeaseController::class, 'store'])->name('store');

    // Disputes come before /{lease} so "disputes" is never read as a lease id.
    Route::get('/disputes', [LeaseDisputeController::class, 'index'])->name('disputes.index');
    Route::get('/disputes/{dispute}', [LeaseDisputeController::class, 'show'])->name('disputes.show');
    Route::post('/disputes/{dispute}/help', [LeaseDisputeController::class, 'help'])->name('disputes.help');
    Route::post('/disputes/{dispute}/escalate', [LeaseDisputeController::class, 'escalate'])->name('disputes.escalate');
    Route::post('/disputes/{dispute}/resolve', [LeaseDisputeController::class, 'resolve'])->name('disputes.resolve');

    Route::get('/{lease}', [LeaseController::class, 'show'])->name('show');
    Route::get('/{lease}/edit', [LeaseController::class, 'edit'])->name('edit');
    Route::put('/{lease}', [LeaseController::class, 'update'])->name('update');
    Route::delete('/{lease}', [LeaseController::class, 'destroy'])->name('destroy');

    Route::post('/{lease}/rent', [RentPaymentController::class, 'store'])->name('rent.store');

    Route::post('/{lease}/disputes', [LeaseDisputeController::class, 'store'])->name('disputes.store');

    Route::post('/{lease}/documents', [LeaseDocumentController::class, 'store'])->name('documents.store');
    Route::get('/{lease}/documents/{media}', [LeaseDocumentController::class, 'show'])->name('documents.show');
    Route::post('/{lease}/model-lease', [LeaseDocumentController::class, 'modelLease'])->name('model-lease');
});
