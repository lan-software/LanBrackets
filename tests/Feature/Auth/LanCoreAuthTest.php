<?php

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

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
        'email' => 'admin@lancore.test',
        'role' => 'admin',
        'exp' => time() + 300,
    ], $overrides);
}

// ─── Callback Tests ───

it('authenticates with valid signed URL', function () {
    config(['services.lancore.auth_secret' => 'test-secret-key']);

    $this->get(signedAuthUrl(validPayload()))
        ->assertRedirect('/');

    $this->assertAuthenticated();

    // Authenticated users are forwarded from the landing page to the dashboard.
    $this->get('/')->assertRedirect(route('dashboard'));
});

it('creates or updates a LanCore user after callback', function () {
    config(['services.lancore.auth_secret' => 'test-secret-key']);

    $this->get(signedAuthUrl(validPayload(['name' => 'Jane Admin', 'role' => 'superadmin'])));

    $user = User::query()->where('lancore_user_id', 1)->first();

    expect($user)->not->toBeNull()
        ->and($user?->name)->toBe('Jane Admin')
        ->and($user?->email)->toBe('admin@lancore.test')
        ->and($user?->role)->toBe(UserRole::Superadmin)
        ->and($user?->lancore_user_id)->toBe(1);
});

it('authenticates through LanCore SSO code exchange', function () {
    config([
        'lancore.enabled' => true,
        'lancore.base_url' => 'http://lancore.test',
        'lancore.internal_url' => null,
        'lancore.token' => 'lci_test_token',
        'lancore.app_slug' => 'lanbrackets',
    ]);

    Http::fake([
        '*/api/integration/sso/exchange' => Http::response([
            'data' => [
                'id' => 42,
                'username' => 'Bracket Admin',
                'email' => 'bracket-admin@example.com',
                'roles' => ['admin'],
            ],
        ]),
    ]);

    $this->get('/auth/callback?code='.str_repeat('a', 64))
        ->assertRedirect('/');

    $this->assertAuthenticated();
    expect(auth()->user()?->email)->toBe('bracket-admin@example.com');
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

it('authenticates regular LanCore users with the signed callback', function () {
    config(['services.lancore.auth_secret' => 'test-secret-key']);

    $this->get(signedAuthUrl(validPayload(['role' => 'user'])))
        ->assertRedirect('/');

    $this->assertAuthenticated();
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
    config(['lancore.enabled' => true]);

    $this->get('/competitions')
        ->assertRedirect(route('auth.redirect'));
});

it('serves the public landing page to guests', function () {
    $this->withoutVite();

    // The root route renders a public Landing page for unauthenticated
    // visitors — auth gating only applies to /dashboard, /competitions, etc.
    $this->get('/')
        ->assertSuccessful();
});

it('allows authenticated access to web UI', function () {
    config(['services.lancore.auth_secret' => 'test-secret-key']);

    $this->get(signedAuthUrl(validPayload()));

    $this->get('/competitions')
        ->assertSuccessful();
});

it('keeps competition management restricted for regular LanCore users', function () {
    config(['services.lancore.auth_secret' => 'test-secret-key']);

    $this->get(signedAuthUrl(validPayload(['role' => 'user'])));

    $this->get('/competitions')
        ->assertForbidden();
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
    $this->assertAuthenticated();

    $this->post('/auth/logout')
        ->assertRedirect(route('login'));

    $this->assertGuest();

    // After logout the guest lands on the public landing page (/ is public).
    $this->get('/')
        ->assertSuccessful();
});
