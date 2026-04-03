<?php

use App\Http\Controllers\Api\V1\CompetitionApiController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(AuthenticateApiToken::class)->group(function () {
    Route::get('competitions', [CompetitionApiController::class, 'index']);
    Route::post('competitions', [CompetitionApiController::class, 'store']);
    Route::get('competitions/{competition}', [CompetitionApiController::class, 'show']);

    Route::get('competitions/{competition}/stages', [CompetitionApiController::class, 'stages']);
    Route::post('competitions/{competition}/participants', [CompetitionApiController::class, 'addParticipant']);

    Route::post('competitions/{competition}/stages/{stage}/generate', [CompetitionApiController::class, 'generate']);
    Route::get('competitions/{competition}/stages/{stage}/matches', [CompetitionApiController::class, 'matches']);

    Route::post('competitions/{competition}/matches/{match}/result', [CompetitionApiController::class, 'reportResult']);

    Route::get('competitions/{competition}/standings', [CompetitionApiController::class, 'standings']);
});
