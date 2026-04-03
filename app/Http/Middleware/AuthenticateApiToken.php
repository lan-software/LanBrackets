<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $apiToken = ApiToken::findByPlainText($bearerToken);

        if ($apiToken === null || ! $apiToken->isValid()) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $apiToken->update(['last_used_at' => now()]);

        return $next($request);
    }
}
