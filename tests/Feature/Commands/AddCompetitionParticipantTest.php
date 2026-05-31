<?php

use App\Enums\ParticipantType;
use App\Models\Competition;
use App\Models\Team;
use App\Models\User;
use Laravel\Prompts\Prompt;

beforeEach(function () {
    // promptForParticipant() uses Laravel\Prompts\select. Route prompts to
    // their non-interactive fallback so the Artisan harness can drive them
    // with expectsQuestion; reset afterwards so other tests are unaffected.
    Prompt::fallbackWhen(true);
});

afterEach(function () {
    Prompt::fallbackWhen(false);
});

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

it('prompts for a team when --id is omitted and adds the selected one', function () {
    $competition = Competition::factory()->create();
    $team = Team::factory()->create(['name' => 'Prompted Squad']);

    $this->artisan('competition:add-participant', [
        'competition' => $competition->id,
        '--type' => 'team',
    ])
        ->expectsQuestion('Select a team', $team->id)
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
        ->expectsQuestion('Select a user', $user->id)
        ->expectsOutputToContain('Participant added: Prompted Player')
        ->assertExitCode(0);

    $cp = $competition->participants()->first();
    expect($cp->participant_type)->toBe(ParticipantType::User);
    expect($cp->participant_id)->toBe($user->id);
});

it('fails when prompting for a team but none exist', function () {
    $competition = Competition::factory()->create();

    expect(Team::count())->toBe(0);

    $this->artisan('competition:add-participant', [
        'competition' => $competition->id,
        '--type' => 'team',
    ])
        ->expectsOutputToContain('No teams found. Create a team first.')
        ->assertExitCode(1);
});

it('fails when prompting for a user but none exist', function () {
    $competition = Competition::factory()->create();

    expect(User::count())->toBe(0);

    $this->artisan('competition:add-participant', [
        'competition' => $competition->id,
        '--type' => 'user',
    ])
        ->expectsOutputToContain('No users found. Create a user first.')
        ->assertExitCode(1);
});
