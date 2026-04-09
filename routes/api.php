<?php

use App\Http\Controllers\Api\LanCoreRolesWebhookController;
use App\Http\Controllers\Api\V1\CompetitionApiController;
use App\Http\Controllers\Api\V1\WebhookConfigController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/roles', LanCoreRolesWebhookController::class)->middleware('lancore.webhook:user.roles_updated')->name('api.webhooks.roles');
Route::post('webhook/roles', LanCoreRolesWebhookController::class)->middleware('lancore.webhook:user.roles_updated');

Route::prefix('v1')->middleware(AuthenticateApiToken::class)->group(function () {
    Route::get('competitions', [CompetitionApiController::class, 'index']);
    Route::post('competitions', [CompetitionApiController::class, 'store']);
    Route::get('competitions/{competition}', [CompetitionApiController::class, 'show']);
    Route::put('competitions/{competition}', [CompetitionApiController::class, 'update']);
    Route::delete('competitions/{competition}', [CompetitionApiController::class, 'destroy']);

    Route::get('competitions/{competition}/stages', [CompetitionApiController::class, 'stages']);
    Route::post('competitions/{competition}/participants', [CompetitionApiController::class, 'addParticipant']);
    Route::post('competitions/{competition}/participants/bulk', [CompetitionApiController::class, 'bulkAddParticipants']);
    Route::delete('competitions/{competition}/participants/{participant}', [CompetitionApiController::class, 'withdrawParticipant']);
    Route::post('competitions/{competition}/participants/{participant}/disqualify', [CompetitionApiController::class, 'disqualifyParticipant']);
    Route::post('competitions/{competition}/share-token', [CompetitionApiController::class, 'regenerateShareToken']);

    Route::post('competitions/{competition}/stages/{stage}/generate', [CompetitionApiController::class, 'generate']);
    Route::post('competitions/{competition}/stages/{stage}/complete', [CompetitionApiController::class, 'completeStage']);
    Route::get('competitions/{competition}/stages/{stage}/matches', [CompetitionApiController::class, 'matches']);

    Route::post('competitions/{competition}/matches/{match}/result', [CompetitionApiController::class, 'reportResult']);
    Route::post('competitions/{competition}/matches/{match}/cancel', [CompetitionApiController::class, 'cancelMatch']);

    Route::get('competitions/{competition}/standings', [CompetitionApiController::class, 'standings']);

    Route::get('webhook', [WebhookConfigController::class, 'show']);
    Route::put('webhook', [WebhookConfigController::class, 'update']);
});
