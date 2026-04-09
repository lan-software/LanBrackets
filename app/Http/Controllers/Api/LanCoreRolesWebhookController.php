<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use LanSoftware\LanCoreClient\Webhooks\Controllers\HandlesLanCoreUserRolesUpdatedWebhook;
use LanSoftware\LanCoreClient\Webhooks\Payloads\UserRolesUpdatedPayload;

class LanCoreRolesWebhookController extends HandlesLanCoreUserRolesUpdatedWebhook
{
    protected function resolveUser(int $lancoreUserId): ?Model
    {
        return User::query()
            ->where('external_provider', 'lancore')
            ->where('external_id', (string) $lancoreUserId)
            ->first();
    }

    protected function syncRoles(Model $user, UserRolesUpdatedPayload $payload): void
    {
        /** @var User $user */
        $role = collect($payload->roles)
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
