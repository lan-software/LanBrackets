<?php

namespace App\Services;

use App\Models\ApiToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatcher
{
    /**
     * Dispatch a webhook to all tokens that have a webhook URL configured.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $event, array $payload): void
    {
        $tokens = ApiToken::query()
            ->whereNotNull('webhook_url')
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get();

        foreach ($tokens as $token) {
            $this->send($token, $event, $payload);
        }
    }

    protected function send(ApiToken $token, string $event, array $payload): void
    {
        $body = json_encode([
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $payload,
        ]);

        $headers = [
            'Content-Type' => 'application/json',
            'X-LanBrackets-Event' => $event,
        ];

        if ($token->webhook_secret !== null) {
            $headers['X-LanBrackets-Signature'] = hash_hmac('sha256', $body, $token->webhook_secret);
        }

        try {
            Http::withHeaders($headers)
                ->timeout(5)
                ->withBody($body, 'application/json')
                ->post($token->webhook_url);
        } catch (\Throwable $e) {
            Log::warning("Webhook delivery failed for token [{$token->id}]: {$e->getMessage()}");
        }
    }
}
