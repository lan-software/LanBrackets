<?php

use App\Enums\ParticipantType;
use App\Models\Competition;
use App\Models\Team;
use App\Models\User;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

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

it('prompts for a team when --id is omitted and adds the selected team', function () {
    $competition = Competition::factory()->create();
    $team = Team::factory()->create(['name' => 'Prompted Squad']);

    // promptForParticipant() uses Laravel\Prompts\select; ENTER picks the
    // first (here only) option.
    Prompt::fake([Key::ENTER]);

    $this->artisan('competition:add-participant', [
        'competition' => $competition->id,
        '--type' => 'team',
    ])
        ->expectsOutputToContain('Participant added: Prompted Squad')
        ->assertExitCode(0);

    $cp = $competition->participants()->first();
    expect($cp->participant_type)->toBe(ParticipantType::Team);
    expect($cp->participant_id)->toBe($team->id);
});

it('prompts for a user when --id is omitted and adds the selected user', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->create(['name' => 'Prompted Player']);

    Prompt::fake([Key::ENTER]);

    $this->artisan('competition:add-participant', [
        'competition' => $competition->id,
        '--type' => 'user',
    ])
        ->expectsOutputToContain('Participant added: Prompted Player')
        ->assertExitCode(0);

    $cp = $competition->participants()->first();
    expect($cp->participant_type)->toBe(ParticipantType::User);
    expect($cp->participant_id)->toBe($user->id);
});

it('errors when prompting for a team but none exist', function () {
    $competition = Competition::factory()->create();

    Prompt::fake();

    $this->artisan('competition:add-participant', [
        'competition' => $competition->id,
        '--type' => 'team',
    ])
        ->expectsOutputToContain('No teams found. Create a team first.')
        ->assertExitCode(1);
});

it('errors when prompting for a user but none exist', function () {
    $competition = Competition::factory()->create();

    Prompt::fake();

    $this->artisan('competition:add-participant', [
        'competition' => $competition->id,
        '--type' => 'user',
    ])
        ->expectsOutputToContain('No users found. Create a user first.')
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
