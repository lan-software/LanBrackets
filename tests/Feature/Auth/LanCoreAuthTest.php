<?php

use App\Models\Competition;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ───

function signedAuthUrl(array $payload, ?string $secret = null, ?string $redirect = null): string
{
    $secret ??= config('services.lancore.auth_secret');
    $encoded = base64_encode(json_encode($payload));
    $signature = hash_hmac('sha256', $encoded, $secret);

    $url = "/auth/callback?payload={$encoded}&signature={$signature}";

    if ($redirect) {
        $url .= '&redirect='.urlencode($redirect);
    }

    return $url;
}

function validPayload(array $overrides = []): array
{
    return array_merge([
        'user_id' => 1,
        'name' => 'Test Admin',
        'role' => 'admin',
        'exp' => time() + 300,
    ], $overrides);
}

// ─── Callback Tests ───

it('authenticates with valid signed URL', function () {
    config(['services.lancore.auth_secret' => 'test-secret-key']);

    $this->get(signedAuthUrl(validPayload()))
        ->assertRedirect('/');

    $this->get('/')->assertSuccessful();
});

it('stores user in session after callback', function () {
    config(['services.lancore.auth_secret' => 'test-secret-key']);

    $this->get(signedAuthUrl(validPayload(['name' => 'Jane Admin', 'role' => 'superadmin'])));

    $this->get('/')
        ->assertSuccessful();

    expect(session('lancore_user'))->toMatchArray([
        'user_id' => 1,
        'name' => 'Jane Admin',
        'role' => 'superadmin',
        'external' => true,
    ]);
});

it('redirects to custom path after callback', function () {
    config(['services.lancore.auth_secret' => 'test-secret-key']);

    $this->get(signedAuthUrl(validPayload(), null, '/competitions'))
        ->assertRedirect('/competitions');
});

it('rejects invalid signature', function () {
    config(['services.lancore.auth_secret' => 'test-secret-key']);

    $payload = base64_encode(json_encode(validPayload()));

    $this->get("/auth/callback?payload={$payload}&signature=bad-signature")
        ->assertForbidden();
});

it('rejects expired token', function () {
    config(['services.lancore.auth_secret' => 'test-secret-key']);

    $this->get(signedAuthUrl(validPayload(['exp' => time() - 60])))
        ->assertForbidden();
});

it('rejects insufficient role', function () {
    config(['services.lancore.auth_secret' => 'test-secret-key']);

    $this->get(signedAuthUrl(validPayload(['role' => 'user'])))
        ->assertForbidden();
});

it('accepts moderator role', function () {
    config(['services.lancore.auth_secret' => 'test-secret-key']);

    $this->get(signedAuthUrl(validPayload(['role' => 'moderator'])))
        ->assertRedirect('/');
});

it('rejects missing parameters', function () {
    $this->get('/auth/callback')
        ->assertForbidden();
});

// ─── Middleware Tests ───

it('blocks unauthenticated access to web UI', function () {
    $this->withoutVite();

    $this->get('/competitions')
        ->assertForbidden();
});

it('blocks unauthenticated access to home page', function () {
    $this->withoutVite();

    $this->get('/')
        ->assertForbidden();
});

it('allows authenticated access to web UI', function () {
    config(['services.lancore.auth_secret' => 'test-secret-key']);

    $this->get(signedAuthUrl(validPayload()));

    $this->get('/competitions')
        ->assertSuccessful();
});

// ─── Overlay remains open ───

it('allows overlay access without authentication', function () {
    $competition = Competition::factory()->tournament()->create([
        'share_token' => 'test-overlay-token',
    ]);

    $this->getJson("/api/v1/overlay/competitions/{$competition->id}?token=test-overlay-token")
        ->assertSuccessful();
});

// ─── Logout ───

it('clears session on logout', function () {
    $this->withoutVite();

    config(['services.lancore.auth_secret' => 'test-secret-key']);

    $this->get(signedAuthUrl(validPayload()));
    expect(session('lancore_user'))->not->toBeNull();

    $this->get('/auth/logout')
        ->assertRedirect('/');

    // After logout, home should be blocked
    $this->get('/')
        ->assertForbidden();
});
