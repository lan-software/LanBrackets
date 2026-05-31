<?php

use App\Enums\MatchStatus;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Generating brackets and auto-playing matches fire webhook events that the
    // listener turns into outbound HTTP calls. Keep the test hermetic.
    Http::fake();
});

it('creates and generates a bracket without auto-playing', function () {
    $this->artisan('competition:test-flow', [
        'format' => 'single_elimination',
        '--participants' => '4',
    ])
        ->expectsOutputToContain('Creating single_elimination competition with 4 participants...')
        ->expectsOutputToContain('Generated bracket:')
        ->expectsOutputToContain('Use --auto to auto-play all matches.')
        ->assertExitCode(0);

    $competition = Competition::where('name', 'like', 'Test single_elimination%')->first();

    expect($competition)->not->toBeNull();
    expect($competition->participants()->count())->toBe(4);
    expect($competition->matches()->count())->toBeGreaterThan(0);
    // No matches played without --auto.
    expect($competition->matches()->where('status', MatchStatus::Finished)->count())->toBe(0);
});

it('auto-plays all matches when --auto is given', function () {
    $this->artisan('competition:test-flow', [
        'format' => 'single_elimination',
        '--participants' => '4',
        '--auto' => true,
    ])
        ->expectsOutputToContain('Auto-playing matches...')
        ->expectsOutputToContain('Completed:')
        ->assertExitCode(0);

    $competition = Competition::where('name', 'like', 'Test single_elimination%')->first();
    $finished = CompetitionMatch::where('competition_id', $competition->id)
        ->where('status', MatchStatus::Finished)
        ->count();

    expect($finished)->toBeGreaterThan(0);
});

it('fails for an invalid format', function () {
    $this->artisan('competition:test-flow', ['format' => 'nonsense'])
        ->expectsOutputToContain('Invalid format: nonsense')
        ->assertExitCode(1);

    expect(Competition::count())->toBe(0);
});
