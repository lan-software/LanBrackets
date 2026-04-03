<?php

use App\Actions\GenerateBracketAction;
use App\Models\ApiToken;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionStage;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ─── Helpers ───

function webhookApiHeaders(?string $token = null): array
{
    return [
        'Authorization' => 'Bearer '.($token ?? 'invalid-token'),
        'Accept' => 'application/json',
    ];
}

function createWebhookApiToken(?string $webhookUrl = null, ?string $webhookSecret = null): string
{
    $result = ApiToken::createToken('Webhook Test Token');
    $result['token']->update([
        'webhook_url' => $webhookUrl,
        'webhook_secret' => $webhookSecret,
    ]);

    return $result['plainText'];
}

function createWebhookCompetition(int $participantCount = 4): Competition
{
    $competition = Competition::factory()->tournament()->create();

    CompetitionStage::factory()->singleElimination()->create([
        'competition_id' => $competition->id,
        'order' => 1,
    ]);

    for ($i = 1; $i <= $participantCount; $i++) {
        CompetitionParticipant::factory()->forTeam(Team::factory()->create())->create([
            'competition_id' => $competition->id,
            'seed' => $i,
        ]);
    }

    return $competition;
}

// ─── Webhook Configuration Tests ───

it('returns webhook configuration', function () {
    $token = createWebhookApiToken('https://lancore.test/webhooks', 'secret1234567890ab');

    $this->getJson('/api/v1/webhook', webhookApiHeaders($token))
        ->assertSuccessful()
        ->assertJsonPath('data.webhook_url', 'https://lancore.test/webhooks')
        ->assertJsonPath('data.has_secret', true);
});

it('returns null webhook when not configured', function () {
    $result = ApiToken::createToken('No Webhook');

    $this->getJson('/api/v1/webhook', webhookApiHeaders($result['plainText']))
        ->assertSuccessful()
        ->assertJsonPath('data.webhook_url', null)
        ->assertJsonPath('data.has_secret', false);
});

it('configures webhook URL via API', function () {
    $result = ApiToken::createToken('Config Test');

    $this->putJson('/api/v1/webhook', [
        'url' => 'https://lancore.test/hooks/lanbrackets',
        'secret' => 'my-webhook-secret-key',
    ], webhookApiHeaders($result['plainText']))
        ->assertSuccessful()
        ->assertJsonPath('data.webhook_url', 'https://lancore.test/hooks/lanbrackets')
        ->assertJsonPath('data.has_secret', true);

    expect($result['token']->fresh()->webhook_url)->toBe('https://lancore.test/hooks/lanbrackets');
});

it('validates webhook URL', function () {
    $result = ApiToken::createToken('Validation Test');

    $this->putJson('/api/v1/webhook', [
        'url' => 'not-a-url',
    ], webhookApiHeaders($result['plainText']))
        ->assertStatus(422);
});

// ─── Webhook Dispatch Tests ───

it('dispatches webhook when bracket is generated', function () {
    Http::fake();

    $token = createWebhookApiToken('https://lancore.test/webhooks', 'test-secret-1234567890');
    $competition = createWebhookCompetition(4);
    $stage = $competition->stages->first();

    $this->postJson(
        "/api/v1/competitions/{$competition->id}/stages/{$stage->id}/generate",
        [],
        webhookApiHeaders($token)
    )->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://lancore.test/webhooks'
            && $request->hasHeader('X-LanBrackets-Event', 'bracket.generated')
            && $request->hasHeader('X-LanBrackets-Signature');
    });
});

it('dispatches webhook when match result is reported', function () {
    Http::fake();

    $token = createWebhookApiToken('https://lancore.test/webhooks', 'test-secret-1234567890');
    $competition = createWebhookCompetition(2);
    $stage = $competition->stages->first();

    app(GenerateBracketAction::class)->execute($stage);
    $match = CompetitionMatch::where('competition_stage_id', $stage->id)->first();

    $this->postJson(
        "/api/v1/competitions/{$competition->id}/matches/{$match->id}/result",
        ['scores' => [1 => 3, 2 => 1]],
        webhookApiHeaders($token)
    )->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-LanBrackets-Event', 'match.result_reported');
    });
});

it('includes HMAC signature in webhook headers', function () {
    Http::fake();

    $secret = 'my-hmac-secret-key-1234';
    $token = createWebhookApiToken('https://lancore.test/webhooks', $secret);
    $competition = createWebhookCompetition(4);
    $stage = $competition->stages->first();

    $this->postJson(
        "/api/v1/competitions/{$competition->id}/stages/{$stage->id}/generate",
        [],
        webhookApiHeaders($token)
    )->assertSuccessful();

    Http::assertSent(function ($request) use ($secret) {
        $signature = $request->header('X-LanBrackets-Signature')[0] ?? null;
        $body = $request->body();

        return $signature === hash_hmac('sha256', $body, $secret);
    });
});

it('skips webhook when no URL is configured', function () {
    Http::fake();

    $result = ApiToken::createToken('No Webhook Token');
    $competition = createWebhookCompetition(4);
    $stage = $competition->stages->first();

    $this->postJson(
        "/api/v1/competitions/{$competition->id}/stages/{$stage->id}/generate",
        [],
        webhookApiHeaders($result['plainText'])
    )->assertSuccessful();

    Http::assertNothingSent();
});
