<?php

use App\Models\Competition;

it('reports when there are no competitions', function () {
    $this->artisan('competition:list')
        ->expectsOutputToContain('No competitions found.')
        ->assertExitCode(0);
});

it('lists existing competitions in a table', function () {
    Competition::factory()->tournament()->create(['name' => 'Listed Cup']);

    // The table renders the competition; the name column is wide enough not to
    // truncate the short name, unlike the type/status columns.
    $this->artisan('competition:list')
        ->expectsOutputToContain('Listed Cup')
        ->doesntExpectOutputToContain('No competitions found.')
        ->assertExitCode(0);
});
