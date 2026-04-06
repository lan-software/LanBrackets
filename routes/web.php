<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\OverlayController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::get('/auth/redirect', [AuthController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');
Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Landing');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::inertia('/dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'role:moderator,admin,superadmin'])->group(function () {
    Route::get('/competitions', [CompetitionController::class, 'index'])->name('competitions.index');
    Route::get('/competitions/{competition}', [CompetitionController::class, 'show'])->name('competitions.show');
});

// Overlay (open — uses share_token auth via query param)
Route::get('/overlay/competitions/{competition}', [OverlayController::class, 'show'])->name('overlay.show');
Route::get('/api/v1/overlay/competitions/{competition}', [OverlayController::class, 'data'])->name('overlay.data');
