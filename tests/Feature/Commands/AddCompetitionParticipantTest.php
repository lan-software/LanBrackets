<?php

use App\Enums\ParticipantType;
use App\Models\Competition;
use App\Models\Team;

it('adds a team to a competition with an explicit seed', function () {
    $competition = Competition::factory()->create();
    $team = Team::factory()->create(['name' => 'Velocity Storm']);

    $this->artisan('competition:add-participant', [
        'competition' => $competition->id,
        '--type' => 'team',
        '--id' => $team->id,
        '--seed' => '3',
    ])
        ->expectsOutputToContain('Participant added: Velocity Storm (seed: 3)')
        ->assertExitCode(0);

    $cp = $competition->participants()->first();

    expect($cp)->not->toBeNull();
    expect($cp->participant_type)->toBe(ParticipantType::Team);
    expect($cp->participant_id)->toBe($team->id);
    expect($cp->seed)->toBe(3);
});

it('auto-assigns a seed when none is provided', function () {
    $competition = Competition::factory()->create();
    $team = Team::factory()->create();

    $this->artisan('competition:add-participant', [
        'competition' => $competition->id,
        '--type' => 'team',
        '--id' => $team->id,
    ])
        ->expectsOutputToContain('(seed: 1)')
        ->assertExitCode(0);

    expect($competition->participants()->first()->seed)->toBe(1);
});

it('fails when the competition does not exist', function () {
    $team = Team::factory()->create();

    $this->artisan('competition:add-participant', [
        'competition' => '01999999999999999999999999',
        '--type' => 'team',
        '--id' => $team->id,
    ])
        ->expectsOutputToContain('Competition not found.')
        ->assertExitCode(1);
});

it('fails when the participant does not exist', function () {
    $competition = Competition::factory()->create();

    $this->artisan('competition:add-participant', [
        'competition' => $competition->id,
        '--type' => 'team',
        '--id' => '01999999999999999999999999',
    ])
        ->expectsOutputToContain('Participant not found')
        ->assertExitCode(1);
});

it('fails when adding the same participant twice', function () {
    $competition = Competition::factory()->create();
    $team = Team::factory()->create();

    $this->artisan('competition:add-participant', [
        'competition' => $competition->id,
        '--type' => 'team',
        '--id' => $team->id,
    ])->assertExitCode(0);

    $this->artisan('competition:add-participant', [
        'competition' => $competition->id,
        '--type' => 'team',
        '--id' => $team->id,
    ])
        ->expectsOutputToContain('is already registered')
        ->assertExitCode(1);
});
