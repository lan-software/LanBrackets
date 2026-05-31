<?php

use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Enums\StageType;
use App\Models\Competition;

it('creates a tournament competition with an initial stage', function () {
    $this->artisan('competition:create', [
        '--name' => 'Spring Cup',
        '--type' => 'tournament',
        '--mode' => 'single_elimination',
    ])
        ->expectsOutputToContain('Competition created: Spring Cup')
        ->assertExitCode(0);

    $competition = Competition::where('name', 'Spring Cup')->with('stages')->first();

    expect($competition)->not->toBeNull();
    expect($competition->type)->toBe(CompetitionType::Tournament);
    expect($competition->status)->toBe(CompetitionStatus::Draft);
    expect($competition->stages)->toHaveCount(1);
    expect($competition->stages->first()->stage_type)->toBe(StageType::SingleElimination);
});

it('creates a league competition with a round robin stage', function () {
    $this->artisan('competition:create', [
        '--name' => 'Summer League',
        '--type' => 'league',
        '--mode' => 'round_robin',
    ])
        ->expectsOutputToContain('Competition created: Summer League')
        ->assertExitCode(0);

    $competition = Competition::where('name', 'Summer League')->with('stages')->first();

    expect($competition->type)->toBe(CompetitionType::League);
    expect($competition->stages->first()->stage_type)->toBe(StageType::RoundRobin);
});

it('fails when an elimination stage is requested for a non-tournament competition', function () {
    $this->artisan('competition:create', [
        '--name' => 'Bad League',
        '--type' => 'league',
        '--mode' => 'single_elimination',
    ])
        ->expectsOutputToContain('only available for tournament competitions')
        ->assertExitCode(1);

    expect(Competition::where('name', 'Bad League')->exists())->toBeFalse();
});
