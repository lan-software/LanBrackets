<?php

use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\OverlayController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('/competitions', [CompetitionController::class, 'index'])->name('competitions.index');
Route::get('/competitions/{competition}', [CompetitionController::class, 'show'])->name('competitions.show');

Route::get('/overlay/competitions/{competition}', [OverlayController::class, 'show'])->name('overlay.show');
Route::get('/api/v1/overlay/competitions/{competition}', [OverlayController::class, 'data'])->name('overlay.data');
