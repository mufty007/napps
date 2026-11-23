<?php

use Illuminate\Support\Facades\Route;
use Modules\Templates\Http\Controllers\TemplateFillController;
use Modules\Templates\Http\Controllers\TemplateLibraryController;

Route::middleware('member')->prefix('templates')->name('templates.')->group(function () {
    Route::get('/', [TemplateLibraryController::class, 'index'])->name('index');

    Route::middleware('can:templates.publish')->group(function () {
        Route::get('/create', [TemplateLibraryController::class, 'create'])->name('create');
        Route::post('/', [TemplateLibraryController::class, 'store'])->name('store');
        Route::get('/{template}/edit', [TemplateLibraryController::class, 'edit'])->name('edit');
        Route::put('/{template}', [TemplateLibraryController::class, 'update'])->name('update');
        Route::post('/{template}/publish', [TemplateLibraryController::class, 'publish'])->name('publish');
        Route::post('/{template}/review', [TemplateLibraryController::class, 'review'])->name('review');
    });

    Route::get('/{template}', [TemplateLibraryController::class, 'show'])->name('show');
    Route::get('/{template}/fill', [TemplateFillController::class, 'create'])->name('fill.create');
    Route::post('/{template}/fill', [TemplateFillController::class, 'store'])->name('fill.store');
});
