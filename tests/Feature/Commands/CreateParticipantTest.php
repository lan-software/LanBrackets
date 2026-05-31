<?php

use App\Models\Team;
use App\Models\User;

it('creates a team participant', function () {
    $this->artisan('participant:create', [
        '--type' => 'team',
        '--name' => 'Iron Vanguard',
        '--tag' => 'IRN',
        '--description' => 'A demo team',
    ])
        ->expectsOutputToContain('Team created: Iron Vanguard')
        ->assertExitCode(0);

    $team = Team::where('name', 'Iron Vanguard')->first();

    expect($team)->not->toBeNull();
    expect($team->tag)->toBe('IRN');
    expect($team->description)->toBe('A demo team');
});

it('creates a user participant with an email', function () {
    $this->artisan('participant:create', [
        '--type' => 'user',
        '--name' => 'Alice Player',
        '--email' => 'alice@example.com',
        '--password' => 'secret-password',
    ])
        ->expectsOutputToContain('User created: Alice Player')
        ->assertExitCode(0);

    $user = User::where('email', 'alice@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Alice Player');
});

it('prompts for the email when creating a user without one', function () {
    $this->artisan('participant:create', [
        '--type' => 'user',
        '--name' => 'Bob Player',
    ])
        ->expectsQuestion('Email address', 'bob@example.com')
        ->expectsOutputToContain('User created: Bob Player')
        ->assertExitCode(0);

    expect(User::where('email', 'bob@example.com')->exists())->toBeTrue();
});
