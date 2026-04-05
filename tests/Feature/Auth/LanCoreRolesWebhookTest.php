<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    config(['lancore.roles_webhook_secret' => 'lanbrackets-webhook-secret']);
});

it('syncs LanBrackets roles from the LanCore webhook payload', function () {
    $user = User::factory()->create([
        'role' => UserRole::User,
        'external' => true,
        'external_provider' => 'lancore',
        'external_id' => '42',
    ]);

    $body = json_encode([
        'event' => 'user.roles_updated',
        'user' => [
            'id' => 42,
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
