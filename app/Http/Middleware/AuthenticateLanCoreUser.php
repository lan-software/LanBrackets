<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateLanCoreUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->session()->get('lancore_user');

        if ($user === null) {
            return Inertia::render('Auth/Unauthenticated', [
                'lancoreUrl' => config('lancore.base_url'),
            ])->toResponse($request)->setStatusCode(403);
        }

        return $next($request);
    }
}
