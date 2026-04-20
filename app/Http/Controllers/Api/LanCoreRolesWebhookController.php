<?php

namespace App\Http\Controllers\Api;

use App\Actions\SyncUserRolesFromLanCore;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use LanSoftware\LanCoreClient\Webhooks\Controllers\HandlesLanCoreUserRolesUpdatedWebhook;
use LanSoftware\LanCoreClient\Webhooks\Payloads\UserRolesUpdatedPayload;

class LanCoreRolesWebhookController extends HandlesLanCoreUserRolesUpdatedWebhook
{
    public function __construct(
        private readonly SyncUserRolesFromLanCore $syncRolesAction,
    ) {}

    protected function resolveUser(int $lancoreUserId): ?Model
    {
        return User::query()->where('lancore_user_id', $lancoreUserId)->first();
    }

    protected function syncRoles(Model $user, UserRolesUpdatedPayload $payload): void
    {
        $this->syncRolesAction->handle($user, $payload->roles);
    }
}
