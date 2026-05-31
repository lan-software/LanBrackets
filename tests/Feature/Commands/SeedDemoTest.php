<?php

use App\Models\Competition;
use App\Models\Team;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Seeding plays through tournaments, firing webhook events that the listener
    // turns into outbound HTTP calls. Keep the test hermetic.
    Http::fake();
});

it('seeds demo tournaments across all formats', function () {
    $this->artisan('db:seed-demo')
        ->expectsOutputToContain('Creating demo teams...')
        ->expectsOutputToContain('Demo seeding complete!')
        ->assertExitCode(0);

    expect(Team::where('name', 'like', 'Demo:%')->count())->toBeGreaterThan(0);
    expect(Competition::where('name', 'like', '[Demo]%')->count())->toBeGreaterThan(0);
    // The single-elimination 16-team demo plays to completion, so matches must exist.
    expect(Competition::where('name', '[Demo] SE 16-Team — Finished')->exists())->toBeTrue();
})->group('slow');
