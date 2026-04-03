<?php

use App\Actions\GenerateBracketAction;
use App\Models\ApiToken;
use App\Models\Competition;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionStage;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ───

function overlayApiHeaders(?string $token = null): array
{
    return [
        'Authorization' => 'Bearer '.($token ?? 'invalid-token'),
        'Accept' => 'application/json',
    ];
}

function createOverlayApiToken(): string
{
    return ApiToken::createToken('Overlay Test Token')['plainText'];
}

function createOverlayCompetition(int $participantCount = 4): Competition
{
    $competition = Competition::factory()->tournament()->create([
        'share_token' => 'test-share-token-abc123',
    ]);

    CompetitionStage::factory()->singleElimination()->create([
        'competition_id' => $competition->id,
        'order' => 1,
    ]);

    for ($i = 1; $i <= $participantCount; $i++) {
        CompetitionParticipant::factory()->forTeam(Team::factory()->create())->create([
            'competition_id' => $competition->id,
            'seed' => $i,
        ]);
    }

    return $competition;
}

// ─── Overlay Data Endpoint Tests ───

it('returns overlay data with valid share token', function () {
    $competition = createOverlayCompetition(4);
    $stage = $competition->stages->first();
    app(GenerateBracketAction::class)->execute($stage);

    $this->getJson("/api/v1/overlay/competitions/{$competition->id}?token=test-share-token-abc123")
        ->assertSuccessful()
        ->assertJsonPath('competition.id', $competition->id)
        ->assertJsonPath('competition.name', $competition->name)
        ->assertJsonStructure([
            'competition' => ['id', 'name', 'slug', 'type', 'status'],
            'stages' => [['id', 'name', 'stage_type', 'status', 'matches']],
        ]);
});

it('rejects overlay data with invalid share token', function () {
    $competition = createOverlayCompetition();

    $this->getJson("/api/v1/overlay/competitions/{$competition->id}?token=wrong-token")
        ->assertForbidden();
});

it('rejects overlay data with missing share token', function () {
    $competition = createOverlayCompetition();

    $this->getJson("/api/v1/overlay/competitions/{$competition->id}")
        ->assertForbidden();
});

// ─── Share Token API Tests ───

it('regenerates share token via API', function () {
    $token = createOverlayApiToken();
    $competition = Competition::factory()->tournament()->create([
        'share_token' => 'old-token',
    ]);

    $response = $this->postJson(
        "/api/v1/competitions/{$competition->id}/share-token",
        [],
        overlayApiHeaders($token)
    )->assertSuccessful();

    $newToken = $response->json('share_token');
    expect($newToken)->not->toBe('old-token')->toHaveLength(32);
    expect($competition->fresh()->share_token)->toBe($newToken);
});

// ─── Overlay Page Tests ───

it('renders overlay page with valid share token', function () {
    $this->withoutVite();

    $competition = createOverlayCompetition(4);
    $stage = $competition->stages->first();
    app(GenerateBracketAction::class)->execute($stage);

    $this->get("/overlay/competitions/{$competition->id}?token=test-share-token-abc123")
        ->assertSuccessful();
});

it('rejects overlay page with invalid share token', function () {
    $competition = createOverlayCompetition();

    $this->get("/overlay/competitions/{$competition->id}?token=bad-token")
        ->assertForbidden();
});
