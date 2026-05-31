<?php

use App\Models\ApiToken;

it('fails when neither --generate nor --token is given', function () {
    $this->artisan('api-token:create', ['name' => 'CI Token'])
        ->expectsOutputToContain('You must pass either --generate or --token=<value>.')
        ->assertExitCode(1);

    expect(ApiToken::where('name', 'CI Token')->exists())->toBeFalse();
});

it('creates a token with a generated value', function () {
    $this->artisan('api-token:create', ['name' => 'Generated Token', '--generate' => true])
        ->expectsOutputToContain('Token created successfully!')
        ->expectsOutputToContain('Generated Token')
        ->assertExitCode(0);

    $token = ApiToken::where('name', 'Generated Token')->first();

    expect($token)->not->toBeNull();
    expect($token->plain_text_prefix)->toStartWith('lbt_');
    // The stored token is the SHA-256 hash of the plain text, never the plain value.
    expect($token->token)->toHaveLength(64);
});

it('creates a token with a provided value and hashes it', function () {
    $this->artisan('api-token:create', ['name' => 'Fixed Token', '--token' => 'my-secret-value'])
        ->expectsOutputToContain('Token created successfully!')
        ->assertExitCode(0);

    $token = ApiToken::where('name', 'Fixed Token')->first();

    expect($token)->not->toBeNull();
    expect($token->token)->toBe(hash('sha256', 'my-secret-value'));
    expect($token->plain_text_prefix)->toBe('my-secre');
});
