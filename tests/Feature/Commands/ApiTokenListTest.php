<?php

use App\Models\ApiToken;

it('reports when there are no api tokens', function () {
    $this->artisan('api-token:list')
        ->expectsOutputToContain('No API tokens found.')
        ->assertExitCode(0);
});

it('lists existing api tokens in a table', function () {
    ApiToken::createToken('Listed Token', 'plain-value-for-listing');

    $this->artisan('api-token:list')
        ->expectsOutputToContain('Listed Token')
        ->assertExitCode(0);
});

it('shows a revoked token in the list', function () {
    $token = ApiToken::createToken('Revoked Token', 'plain-revoked')['token'];
    $token->update(['revoked_at' => now()]);

    $this->artisan('api-token:list')
        ->expectsOutputToContain('Revoked Token')
        ->assertExitCode(0);
});
