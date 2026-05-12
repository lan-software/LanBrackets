<?php

use App\Models\ApiToken;
use App\Models\Team;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─── Helpers ───

function teamApiHeaders(?string $token = null): array
{
    return [
        'Authorization' => 'Bearer '.($token ?? 'invalid-token'),
        'Accept' => 'application/json',
    ];
}

function createTeamApiToken(): string
{
    $result = ApiToken::createToken('Test Token');

    return $result['plainText'];
}

// ─── Authentication ───

it('rejects unauthenticated requests to upsert teams', function () {
    $this->postJson('/api/v1/teams', [
        'name' => 'Falcons',
        'external_reference_id' => '1',
        'source_system' => 'lancore',
    ])->assertStatus(401);
});

// ─── Create / Update ───

it('creates a new team via upsert (201)', function () {
    $token = createTeamApiToken();

    $response = $this->postJson('/api/v1/teams', [
        'name' => 'Falcons',
        'tag' => 'FAL',
        'external_reference_id' => '1',
        'source_system' => 'lancore',
    ], teamApiHeaders($token));

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Falcons')
        ->assertJsonPath('data.tag', 'FAL')
        ->assertJsonPath('data.external_reference_id', '1')
        ->assertJsonPath('data.source_system', 'lancore');

    $this->assertDatabaseHas('teams', [
        'name' => 'Falcons',
        'external_reference_id' => '1',
        'source_system' => 'lancore',
    ]);
});

it('updates an existing team on second upsert (200)', function () {
    $token = createTeamApiToken();

    $this->postJson('/api/v1/teams', [
        'name' => 'Falcons',
        'external_reference_id' => '1',
        'source_system' => 'lancore',
    ], teamApiHeaders($token))->assertStatus(201);

    $response = $this->postJson('/api/v1/teams', [
        'name' => 'Falcons Updated',
        'tag' => 'FU',
        'external_reference_id' => '1',
        'source_system' => 'lancore',
    ], teamApiHeaders($token));

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Falcons Updated')
        ->assertJsonPath('data.tag', 'FU');

    expect(Team::query()->bySource('lancore', '1')->count())->toBe(1);
});

it('treats different source_systems with same external_reference_id as separate teams', function () {
    $token = createTeamApiToken();

    $this->postJson('/api/v1/teams', [
        'name' => 'Lancore Team',
        'external_reference_id' => '42',
        'source_system' => 'lancore',
    ], teamApiHeaders($token))->assertStatus(201);

    $this->postJson('/api/v1/teams', [
        'name' => 'Other Team',
        'external_reference_id' => '42',
        'source_system' => 'other',
    ], teamApiHeaders($token))->assertStatus(201);

    expect(Team::query()->where('external_reference_id', '42')->count())->toBe(2);
});

// ─── Validation ───

it('rejects upsert missing source_system', function () {
    $token = createTeamApiToken();

    $this->postJson('/api/v1/teams', [
        'name' => 'Falcons',
        'external_reference_id' => '1',
    ], teamApiHeaders($token))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['source_system']);
});

it('rejects upsert missing external_reference_id', function () {
    $token = createTeamApiToken();

    $this->postJson('/api/v1/teams', [
        'name' => 'Falcons',
        'source_system' => 'lancore',
    ], teamApiHeaders($token))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['external_reference_id']);
});

it('rejects upsert with invalid status', function () {
    $token = createTeamApiToken();

    $this->postJson('/api/v1/teams', [
        'name' => 'Falcons',
        'external_reference_id' => '1',
        'source_system' => 'lancore',
        'status' => 'banana',
    ], teamApiHeaders($token))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('accepts null tag and description on upsert', function () {
    $token = createTeamApiToken();

    $this->postJson('/api/v1/teams', [
        'name' => 'Sparse Team',
        'tag' => null,
        'description' => null,
        'external_reference_id' => '7',
        'source_system' => 'lancore',
    ], teamApiHeaders($token))
        ->assertStatus(201)
        ->assertJsonPath('data.tag', null)
        ->assertJsonPath('data.description', null);
});

// ─── Show ───

it('returns a team via GET /teams/{team}', function () {
    $token = createTeamApiToken();
    $team = Team::factory()->create();

    $this->getJson("/api/v1/teams/{$team->id}", teamApiHeaders($token))
        ->assertStatus(200)
        ->assertJsonPath('data.id', $team->id);
});

it('returns 404 on unknown team', function () {
    $token = createTeamApiToken();

    $this->getJson('/api/v1/teams/999999', teamApiHeaders($token))
        ->assertStatus(404);
});

// ─── Partial unique index (Postgres-only) ───

test('partial unique index prevents duplicate (source_system, external_reference_id)', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('requires postgres for partial unique index');
    }

    Team::factory()->create([
        'source_system' => 'lancore',
        'external_reference_id' => '99',
    ]);

    expect(function () {
        Team::factory()->create([
            'source_system' => 'lancore',
            'external_reference_id' => '99',
        ]);
    })->toThrow(UniqueConstraintViolationException::class);
});
