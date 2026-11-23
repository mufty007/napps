<?php

use Illuminate\Support\Facades\Route;
use Modules\Records\Http\Controllers\DebtorCheckController;
use Modules\Records\Http\Controllers\StaffMemberController;
use Modules\Records\Http\Controllers\StudentController;
use Modules\Records\Http\Controllers\StudentDocumentController;
use Modules\Records\Http\Controllers\TransferRequestController;

// Access is decided by the policies (active role + school scope), not by route middleware.
Route::middleware('member')->prefix('records')->name('records.')->group(function () {
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

    Route::post('/students/{student}/documents', [StudentDocumentController::class, 'store'])->name('students.documents.store');
    Route::get('/students/{student}/documents/{media}', [StudentDocumentController::class, 'show'])->name('students.documents.show');
    Route::delete('/students/{student}/documents/{media}', [StudentDocumentController::class, 'destroy'])->name('students.documents.destroy');

    Route::get('/staff', [StaffMemberController::class, 'index'])->name('staff.index');
    Route::get('/staff/create', [StaffMemberController::class, 'create'])->name('staff.create');
    Route::post('/staff', [StaffMemberController::class, 'store'])->name('staff.store');
    Route::get('/staff/{member}/edit', [StaffMemberController::class, 'edit'])->name('staff.edit');
    Route::put('/staff/{member}', [StaffMemberController::class, 'update'])->name('staff.update');
    Route::delete('/staff/{member}', [StaffMemberController::class, 'destroy'])->name('staff.destroy');

    Route::get('/transfers', [TransferRequestController::class, 'index'])->name('transfers.index');
    Route::get('/transfers/create', [TransferRequestController::class, 'create'])->name('transfers.create');
    Route::post('/transfers', [TransferRequestController::class, 'store'])->middleware('throttle:20,1')->name('transfers.store');
    Route::get('/transfers/{transfer}', [TransferRequestController::class, 'show'])->name('transfers.show');
    Route::post('/transfers/{transfer}/respond', [TransferRequestController::class, 'respond'])->name('transfers.respond');
    Route::post('/transfers/{transfer}/cancel', [TransferRequestController::class, 'cancel'])->name('transfers.cancel');

    Route::get('/debtor-check', [DebtorCheckController::class, 'index'])->name('debtor-check.index');
    Route::post('/debtor-check', [DebtorCheckController::class, 'check'])->middleware('throttle:30,1')->name('debtor-check.check');
});
