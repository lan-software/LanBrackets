<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var ApiToken $token */
        $token = $request->attributes->get('api_token');

        return response()->json([
            'data' => [
                'webhook_url' => $token->webhook_url,
                'has_secret' => $token->webhook_secret !== null,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'secret' => ['nullable', 'string', 'min:16', 'max:255'],
        ]);

        /** @var ApiToken $token */
        $token = $request->attributes->get('api_token');

        $token->update([
            'webhook_url' => $validated['url'],
            'webhook_secret' => $validated['secret'] ?? $token->webhook_secret,
        ]);

        return response()->json([
            'data' => [
                'webhook_url' => $token->webhook_url,
                'has_secret' => $token->webhook_secret !== null,
            ],
        ]);
    }
}
