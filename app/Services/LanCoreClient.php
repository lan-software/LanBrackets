<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LanCoreClient
{
    public function ssoAuthorizeUrl(): string
    {
        $this->ensureEnabled();

        return rtrim((string) config('lancore.base_url'), '/')
            .'/sso/authorize?'
            .http_build_query([
                'app' => config('lancore.app_slug'),
                'redirect_uri' => config('lancore.callback_url'),
            ]);
    }

    /**
     * @return array{id:int, username:string, email:?string, roles:array<int,string>}
     */
    public function exchangeCode(string $code): array
    {
        $this->ensureEnabled();

        try {
            $response = $this->http()->post('/api/integration/sso/exchange', [
                'code' => $code,
            ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('LanCore is unreachable.', 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException((string) ($response->json('error') ?? 'SSO exchange failed.'), $response->status());
        }

        $data = $response->json('data');

        if (! is_array($data) || ! isset($data['id'], $data['username'])) {
            throw new RuntimeException('Invalid LanCore user payload.');
        }

        return [
            'id' => (int) $data['id'],
            'username' => (string) $data['username'],
            'email' => isset($data['email']) && is_string($data['email']) ? $data['email'] : null,
            'roles' => array_values(array_filter($data['roles'] ?? [], 'is_string')),
        ];
    }

    private function ensureEnabled(): void
    {
        if (! config('lancore.enabled')) {
            throw new RuntimeException('LanCore integration is disabled.');
        }
    }

    private function http()
    {
        return Http::baseUrl((string) (config('lancore.internal_url') ?? config('lancore.base_url')))
            ->timeout((int) config('lancore.timeout', 5))
            ->retry((int) config('lancore.retries', 2), (int) config('lancore.retry_delay', 100))
            ->withToken((string) config('lancore.token'));
    }
}
