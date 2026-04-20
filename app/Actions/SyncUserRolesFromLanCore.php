<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;

class SyncUserRolesFromLanCore
{
    /**
     * @param  array<int, string>  $roles
     */
    public function handle(User $user, array $roles): void
    {
        $role = collect($roles)
            ->map(fn (string $incomingRole) => UserRole::tryFrom($incomingRole))
            ->filter()
            ->sortByDesc(fn (UserRole $mappedRole) => match ($mappedRole) {
                UserRole::Superadmin => 4,
                UserRole::Admin => 3,
                UserRole::Moderator => 2,
                UserRole::User => 1,
            })
            ->first() ?? UserRole::User;

        if ($user->role !== $role) {
            $user->role = $role;
            $user->save();
        }
    }
}
