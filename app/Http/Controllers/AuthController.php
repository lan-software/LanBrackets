<?php

namespace App\Http\Controllers;

use App\Actions\SyncUserRolesFromLanCore;
use App\Services\UserSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use LanSoftware\LanCoreClient\DTOs\LanCoreUser;
use LanSoftware\LanCoreClient\Exceptions\LanCoreException;
use LanSoftware\LanCoreClient\Exceptions\LanCoreRequestException;
use LanSoftware\LanCoreClient\LanCoreClient;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly LanCoreClient $client,
        private readonly UserSyncService $syncService,
        private readonly SyncUserRolesFromLanCore $syncRoles,
    ) {}

    public function redirect(): Response
    {
        try {
            return Inertia::location($this->client->ssoAuthorizeUrl());
        } catch (LanCoreException) {
            return redirect()->route('login', ['local' => 1]);
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('code')) {
            return $this->handleSsoCallback($request);
        }

        return $this->handleSignedCallback($request);
    }

    private function handleSsoCallback(Request $request): RedirectResponse
    {
        $code = $request->string('code')->toString();

        if (strlen($code) !== 64) {
            return redirect()->route('login')->with('error', __('auth.sso_callback_invalid'));
        }

        try {
            $lanCoreUser = $this->client->exchangeCode($code);
            $user = $this->syncService->resolveFromLanCore($lanCoreUser);
            $this->syncRoles->handle($user, $lanCoreUser->roles);
        } catch (LanCoreRequestException $e) {
            return redirect()->route('login')->with('error', $e->statusCode === 400
                ? __('auth.sso_link_expired')
                : __('auth.auth_service_unavailable'));
        } catch (LanCoreException) {
            return redirect()->route('login')->with('error', __('auth.auth_service_unavailable'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    private function handleSignedCallback(Request $request): RedirectResponse
    {
        $payload = $request->query('payload');
        $signature = $request->query('signature');

        abort_unless($payload && $signature, 403, 'Missing authentication parameters.');

        $secret = config('lancore.legacy_auth_secret');
        abort_unless($secret, 500, 'LanCore auth secret is not configured.');

        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        abort_unless(hash_equals($expectedSignature, $signature), 403, 'Invalid signature.');

        $decoded = json_decode(base64_decode($payload), true);
        abort_unless(is_array($decoded), 403, 'Invalid payload.');

        abort_unless(
            isset($decoded['exp']) && $decoded['exp'] > time(),
            403,
            'Authentication link has expired.',
        );

        abort_unless(
            isset($decoded['role']) || isset($decoded['roles']),
            403,
            'Missing role information.',
        );

        abort_unless(
            isset($decoded['user_id']) && is_numeric($decoded['user_id']),
            403,
            'Missing user identifier.',
        );

        if (isset($decoded['role']) && is_string($decoded['role'])) {
            $roles = [$decoded['role']];
        } elseif (is_array($decoded['roles'] ?? null)) {
            $roles = array_values(array_filter($decoded['roles'], 'is_string'));
        } else {
            $roles = [];
        }

        $email = isset($decoded['email']) && is_string($decoded['email'])
            ? strtolower($decoded['email'])
            : null;

        $lanCoreUser = new LanCoreUser(
            id: (int) $decoded['user_id'],
            username: is_string($decoded['name'] ?? null) ? $decoded['name'] : 'Unknown',
            email: $email,
            roles: $roles,
        );

        $user = $this->syncService->resolveFromLanCore($lanCoreUser);
        $this->syncRoles->handle($user, $roles);

        Auth::login($user);
        $request->session()->regenerate();

        $redirect = $request->query('redirect', '/');

        return redirect()->to($redirect);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
