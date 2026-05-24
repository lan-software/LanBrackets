<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function lanBracketsRolesWebhookHeaders(string $body, string $secret): array
{
    return [
        'X-Webhook-Event' => 'user.roles_updated',
        'X-Webhook-Signature' => 'sha256='.hash_hmac('sha256', $body, $secret),
        'Content-Type' => 'application/json',
    ];
}

beforeEach(function () {
    config(['lancore.webhooks.secret' => 'lanbrackets-webhook-secret']);
});

it('syncs LanBrackets roles from the LanCore webhook payload', function () {
    $lancoreUserId = (string) Str::ulid();
    $user = User::factory()->lanCoreUser($lancoreUserId)->create([
        'role' => UserRole::User,
    ]);

    $body = json_encode([
        'event' => 'user.roles_updated',
        'user' => [
            'id' => $lancoreUserId,
            'username' => $user->name,
            'roles' => ['superadmin'],
        ],
        'changes' => [
            'added' => ['superadmin'],
            'removed' => ['user'],
        ],
    ], JSON_THROW_ON_ERROR);

    $this->postJson('/api/webhooks/roles', json_decode($body, true), lanBracketsRolesWebhookHeaders($body, 'lanbrackets-webhook-secret'))
        ->assertOk();

    expect($user->fresh()->role)->toBe(UserRole::Superadmin);
});
