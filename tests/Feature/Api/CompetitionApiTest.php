<?php

use App\Actions\GenerateBracketAction;
use App\Domain\Competition\Formats\SingleElimination\Resolver;
use App\Models\ApiToken;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionStage;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helper ───

function apiHeaders(?string $token = null): array
{
    return [
        'Authorization' => 'Bearer '.($token ?? 'invalid-token'),
        'Accept' => 'application/json',
    ];
}

function createApiToken(): string
{
    $result = ApiToken::createToken('Test Token');

    return $result['plainText'];
}

function createCompetitionWithParticipants(int $count = 4): Competition
{
    $competition = Competition::factory()->tournament()->create();

    CompetitionStage::factory()->singleElimination()->create([
        'competition_id' => $competition->id,
        'order' => 1,
    ]);

    for ($i = 1; $i <= $count; $i++) {
        CompetitionParticipant::factory()->forTeam(Team::factory()->create())->create([
            'competition_id' => $competition->id,
            'seed' => $i,
        ]);
    }

    return $competition;
}

// ─── Authentication Tests ───

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/v1/competitions')
        ->assertStatus(401);
});

it('rejects invalid tokens', function () {
    $this->getJson('/api/v1/competitions', apiHeaders('bad-token'))
        ->assertStatus(401);
});

it('rejects revoked tokens', function () {
    $result = ApiToken::createToken('Revoked');
    $result['token']->update(['revoked_at' => now()]);

    $this->getJson('/api/v1/competitions', apiHeaders($result['plainText']))
        ->assertStatus(401);
});

it('rejects expired tokens', function () {
    $result = ApiToken::createToken('Expired');
    $result['token']->update(['expires_at' => now()->subDay()]);

    $this->getJson('/api/v1/competitions', apiHeaders($result['plainText']))
        ->assertStatus(401);
});

it('accepts valid tokens', function () {
    $token = createApiToken();

    $this->getJson('/api/v1/competitions', apiHeaders($token))
        ->assertSuccessful();
});

it('updates last_used_at on successful auth', function () {
    $result = ApiToken::createToken('Test');
    $token = $result['plainText'];

    expect($result['token']->last_used_at)->toBeNull();

    $this->getJson('/api/v1/competitions', apiHeaders($token))
        ->assertSuccessful();

    expect($result['token']->fresh()->last_used_at)->not->toBeNull();
});

// ─── Competition CRUD Tests ───

it('lists competitions', function () {
    $token = createApiToken();
    Competition::factory()->count(3)->create();

    $this->getJson('/api/v1/competitions', apiHeaders($token))
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('creates a competition', function () {
    $token = createApiToken();

    $response = $this->postJson('/api/v1/competitions', [
        'name' => 'Test Tournament',
        'type' => 'tournament',
        'stage_type' => 'single_elimination',
    ], apiHeaders($token));

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Test Tournament')
        ->assertJsonPath('data.type', 'tournament');

    $this->assertDatabaseHas('competitions', ['name' => 'Test Tournament']);
});

it('validates required fields on create', function () {
    $token = createApiToken();

    $this->postJson('/api/v1/competitions', [], apiHeaders($token))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'type', 'stage_type']);
});

it('shows a competition', function () {
    $token = createApiToken();
    $competition = Competition::factory()->tournament()->create();

    $this->getJson("/api/v1/competitions/{$competition->id}", apiHeaders($token))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $competition->id);
});

// ─── Participant Tests ───

it('adds a participant', function () {
    $token = createApiToken();
    $competition = Competition::factory()->tournament()->create();
    CompetitionStage::factory()->singleElimination()->create([
        'competition_id' => $competition->id,
    ]);
    $team = Team::factory()->create();

    $this->postJson("/api/v1/competitions/{$competition->id}/participants", [
        'participant_type' => 'team',
        'participant_id' => $team->id,
    ], apiHeaders($token))
        ->assertStatus(201)
        ->assertJsonPath('data.participant_id', $team->id);
});

it('validates participant request', function () {
    $token = createApiToken();
    $competition = Competition::factory()->tournament()->create();

    $this->postJson("/api/v1/competitions/{$competition->id}/participants", [], apiHeaders($token))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['participant_type', 'participant_id']);
});

// ─── Stage & Bracket Tests ───

it('lists stages', function () {
    $token = createApiToken();
    $competition = createCompetitionWithParticipants();

    $this->getJson("/api/v1/competitions/{$competition->id}/stages", apiHeaders($token))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('generates a bracket', function () {
    $token = createApiToken();
    $competition = createCompetitionWithParticipants(4);
    $stage = $competition->stages->first();

    $this->postJson(
        "/api/v1/competitions/{$competition->id}/stages/{$stage->id}/generate",
        [],
        apiHeaders($token)
    )
        ->assertSuccessful()
        ->assertJsonPath('message', 'Bracket generated successfully.');

    expect(CompetitionMatch::where('competition_stage_id', $stage->id)->count())->toBe(3);
});

it('lists matches for a stage', function () {
    $token = createApiToken();
    $competition = createCompetitionWithParticipants(4);
    $stage = $competition->stages->first();

    app(GenerateBracketAction::class)->execute($stage);

    $this->getJson(
        "/api/v1/competitions/{$competition->id}/stages/{$stage->id}/matches",
        apiHeaders($token)
    )
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

// ─── Match Result Tests ───

it('reports a match result', function () {
    $token = createApiToken();
    $competition = createCompetitionWithParticipants(2);
    $stage = $competition->stages->first();

    app(GenerateBracketAction::class)->execute($stage);

    $match = CompetitionMatch::where('competition_stage_id', $stage->id)->first();

    $this->postJson(
        "/api/v1/competitions/{$competition->id}/matches/{$match->id}/result",
        ['scores' => [1 => 3, 2 => 1]],
        apiHeaders($token)
    )
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'finished');
});

it('validates scores on result report', function () {
    $token = createApiToken();
    $competition = createCompetitionWithParticipants(2);
    $stage = $competition->stages->first();

    app(GenerateBracketAction::class)->execute($stage);

    $match = CompetitionMatch::where('competition_stage_id', $stage->id)->first();

    $this->postJson(
        "/api/v1/competitions/{$competition->id}/matches/{$match->id}/result",
        [],
        apiHeaders($token)
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors(['scores']);
});

// ─── Standings Tests ───

it('returns standings for a competition', function () {
    $token = createApiToken();
    $competition = createCompetitionWithParticipants(2);
    $stage = $competition->stages->first();

    app(GenerateBracketAction::class)->execute($stage);

    $match = CompetitionMatch::where('competition_stage_id', $stage->id)->first();
    $match->matchParticipants->firstWhere('slot', 1)->update(['score' => 3]);
    $match->matchParticipants->firstWhere('slot', 2)->update(['score' => 1]);
    app(Resolver::class)->resolve($match);

    $this->getJson("/api/v1/competitions/{$competition->id}/standings", apiHeaders($token))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.wins', 1);
});
