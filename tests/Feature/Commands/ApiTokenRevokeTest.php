<?php

use App\Models\ApiToken;

it('revokes an existing api token', function () {
    $token = ApiToken::createToken('Revoke Me', 'plain-revoke-me')['token'];

    expect($token->revoked_at)->toBeNull();

    $this->artisan('api-token:revoke', ['id' => $token->id])
        ->expectsOutputToContain("Token [{$token->name}] has been revoked.")
        ->assertExitCode(0);

    expect($token->fresh()->revoked_at)->not->toBeNull();
});

it('warns when the token is already revoked', function () {
    $token = ApiToken::createToken('Already Revoked', 'plain-already')['token'];
    $token->update(['revoked_at' => now()]);

    $this->artisan('api-token:revoke', ['id' => $token->id])
        ->expectsOutputToContain('Token is already revoked.')
        ->assertExitCode(0);
});

it('fails when the token does not exist', function () {
    $this->artisan('api-token:revoke', ['id' => '01999999999999999999999999'])
        ->expectsOutputToContain('Token not found.')
        ->assertExitCode(1);
});
