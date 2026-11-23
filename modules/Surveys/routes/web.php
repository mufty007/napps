<?php

use Illuminate\Support\Facades\Route;
use Modules\Surveys\Http\Controllers\SurveyController;
use Modules\Surveys\Http\Controllers\SurveyQuestionController;
use Modules\Surveys\Http\Controllers\SurveyRespondController;
use Modules\Surveys\Http\Controllers\SurveyResultsController;

Route::middleware('member')->group(function () {
    // Schools answer surveys addressed to them (proprietor, once per school).
    Route::middleware('can:surveys.respond')->prefix('surveys')->name('surveys.respond.')->group(function () {
        Route::get('/', [SurveyRespondController::class, 'index'])->name('index');
        Route::get('/{survey}/respond', [SurveyRespondController::class, 'show'])->name('show');
        Route::post('/{survey}/respond', [SurveyRespondController::class, 'store'])->name('store');
    });

    // Executives build, run and read surveys.
    Route::middleware('can:surveys.issue')->prefix('manage/surveys')->name('surveys.')->group(function () {
        Route::get('/', [SurveyController::class, 'index'])->name('index');
        Route::get('/create', [SurveyController::class, 'create'])->name('create');
        Route::post('/', [SurveyController::class, 'store'])->name('store');
        Route::get('/{survey}/edit', [SurveyController::class, 'edit'])->name('edit');
        Route::put('/{survey}', [SurveyController::class, 'update'])->name('update');
        Route::delete('/{survey}', [SurveyController::class, 'destroy'])->name('destroy');
        Route::post('/{survey}/publish', [SurveyController::class, 'publish'])->name('publish');
        Route::post('/{survey}/close', [SurveyController::class, 'close'])->name('close');

        Route::post('/{survey}/questions', [SurveyQuestionController::class, 'store'])->name('questions.store');
        Route::delete('/{survey}/questions/{question}', [SurveyQuestionController::class, 'destroy'])->name('questions.destroy');
        Route::post('/{survey}/questions/{question}/move', [SurveyQuestionController::class, 'move'])->name('questions.move');

        Route::get('/{survey}/results', [SurveyResultsController::class, 'show'])->name('results');
        Route::get('/{survey}/results/export', [SurveyResultsController::class, 'export'])->name('results.export');
    });
});
