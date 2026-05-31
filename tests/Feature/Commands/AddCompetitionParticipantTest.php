<?php

use App\Enums\ParticipantType;
use App\Models\Competition;
use App\Models\Team;
use App\Models\User;

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

it('prompts for a team when --id is omitted and adds the selected one', function () {
    $competition = Competition::factory()->create();
    $team = Team::factory()->create(['name' => 'Prompted Squad']);

    // Omitting --id falls through to promptForParticipant(), which presents a
    // Laravel\Prompts select() that the Artisan test harness drives via
    // expectsChoice (the option key is the model id).
    $this->artisan('competition:add-participant', [
        'competition' => $competition->id,
        '--type' => 'team',
    ])
        ->expectsChoice('Select a team', $team->id, [$team->id => "{$team->name} (ID: {$team->id})"])
        ->expectsOutputToContain('Participant added: Prompted Squad')
        ->assertExitCode(0);

    $cp = $competition->participants()->first();
    expect($cp->participant_type)->toBe(ParticipantType::Team);
    expect($cp->participant_id)->toBe($team->id);
});

it('prompts for a user when --id is omitted and adds the selected one', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->create(['name' => 'Prompted Player']);

    $this->artisan('competition:add-participant', [
        'competition' => $competition->id,
        '--type' => 'user',
    ])
        ->expectsChoice('Select a user', $user->id, [$user->id => "{$user->name} (ID: {$user->id})"])
        ->expectsOutputToContain('Participant added: Prompted Player')
        ->assertExitCode(0);

    $cp = $competition->participants()->first();
    expect($cp->participant_type)->toBe(ParticipantType::User);
    expect($cp->participant_id)->toBe($user->id);
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
