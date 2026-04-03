<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\OverlayController;
use App\Http\Middleware\AuthenticateLanCoreUser;
use Illuminate\Support\Facades\Route;

// Auth (public)
Route::get('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');
Route::get('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

// Protected web UI
Route::middleware(AuthenticateLanCoreUser::class)->group(function () {
    Route::inertia('/', 'Welcome')->name('home');
    Route::get('/competitions', [CompetitionController::class, 'index'])->name('competitions.index');
    Route::get('/competitions/{competition}', [CompetitionController::class, 'show'])->name('competitions.show');
});

// Overlay (open — uses share_token auth via query param)
Route::get('/overlay/competitions/{competition}', [OverlayController::class, 'show'])->name('overlay.show');
Route::get('/api/v1/overlay/competitions/{competition}', [OverlayController::class, 'data'])->name('overlay.data');
