<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 403);

        $allowedRoles = array_map(
            static fn (string $role) => UserRole::from($role),
            $roles,
        );

        abort_unless($user->hasAnyRole($allowedRoles), 403);

        return $next($request);
    }
}
