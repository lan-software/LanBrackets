<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LanCoreRolesWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $body = $request->getContent();
        $secret = (string) config('lancore.roles_webhook_secret', '');
        $signature = $request->header('X-Webhook-Signature');

        if ($secret !== '') {
            abort_unless(is_string($signature) && str_starts_with($signature, 'sha256='), 403, 'Invalid signature.');

            $expected = 'sha256='.hash_hmac('sha256', $body, $secret);
            abort_unless(hash_equals($expected, $signature), 403, 'Invalid signature.');
        }

        abort_unless($request->header('X-Webhook-Event') === 'user.roles_updated', 400, 'Unsupported webhook event.');

        $userId = (string) $request->integer('user.id');
        $roles = $request->input('user.roles');

        abort_unless($userId !== '0' && is_array($roles), 422, 'Invalid payload.');

        $user = User::query()
            ->where('external_provider', 'lancore')
            ->where('external_id', $userId)
            ->first();

        if ($user === null) {
            return response()->json(['status' => 'ignored'], 202);
        }

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

        return response()->json(['status' => 'ok']);
    }
}
