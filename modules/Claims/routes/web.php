<?php

use Illuminate\Support\Facades\Route;
use Modules\Claims\Http\Controllers\ClaimCaseController;
use Modules\Claims\Http\Controllers\ClaimDocumentController;
use Modules\Claims\Http\Controllers\ClaimHearingController;
use Modules\Claims\Http\Controllers\ClaimSettlementController;
use Modules\Claims\Http\Controllers\StateClaimSettingController;

// Access is decided by ClaimCasePolicy (active role + scope), not by route middleware.
Route::middleware('member')->prefix('claims')->name('claims.')->group(function () {
    Route::get('/', [ClaimCaseController::class, 'index'])->name('index');
    Route::get('/create', [ClaimCaseController::class, 'create'])->name('create');
    Route::post('/', [ClaimCaseController::class, 'store'])->name('store');

    Route::get('/settings/states', [StateClaimSettingController::class, 'index'])->name('settings.states');
    Route::put('/settings/states/{state}', [StateClaimSettingController::class, 'update'])->name('settings.states.update');

    Route::get('/{case}', [ClaimCaseController::class, 'show'])->name('show');
    Route::put('/{case}', [ClaimCaseController::class, 'update'])->name('update');
    Route::post('/{case}/notes', [ClaimCaseController::class, 'note'])->name('notes.store');
    Route::post('/{case}/assign', [ClaimCaseController::class, 'assign'])->name('assign');

    Route::post('/{case}/documents', [ClaimDocumentController::class, 'store'])->name('documents.store');
    Route::get('/{case}/documents/{media}', [ClaimDocumentController::class, 'show'])->name('documents.show');
    Route::post('/{case}/demand-letter', [ClaimDocumentController::class, 'demandLetter'])->name('demand-letter');

    Route::post('/{case}/hearings', [ClaimHearingController::class, 'store'])->name('hearings.store');
    Route::put('/{case}/hearings/{hearing}', [ClaimHearingController::class, 'update'])->name('hearings.update');

    Route::post('/{case}/settlements', [ClaimSettlementController::class, 'store'])->name('settlements.store');
    Route::post('/{case}/fees', [ClaimSettlementController::class, 'fee'])->name('fees.store');
});
