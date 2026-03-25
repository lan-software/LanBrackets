<?php

use App\Http\Controllers\CompetitionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('/competitions', [CompetitionController::class, 'index'])->name('competitions.index');
Route::get('/competitions/{competition}', [CompetitionController::class, 'show'])->name('competitions.show');
