<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use LanSoftware\LanCoreClient\DTOs\LanCoreUser;
use LanSoftware\LanCoreClient\Exceptions\LanCoreException;
use LanSoftware\LanCoreClient\Exceptions\LanCoreRequestException;
use LanSoftware\LanCoreClient\LanCoreClient;

class AuthController extends Controller
{
    public function __construct(private readonly LanCoreClient $client) {}

    public function redirect(): \Symfony\Component\HttpFoundation\Response
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
            return redirect()->route('login')->with('error', 'Invalid SSO callback. Please try again.');
        }

        try {
            $lanCoreUser = $this->client->exchangeCode($code);
            $role = $this->resolveRoleFromLanCore($lanCoreUser->roles);

            $user = $this->upsertLanCoreUser($lanCoreUser, $role);
        } catch (LanCoreRequestException $e) {
            return redirect()->route('login')->with('error', $e->statusCode === 400
                ? 'The login link has expired. Please try again.'
                : 'Could not connect to authentication service. Please try again later.');
        } catch (LanCoreException) {
            return redirect()->route('login')->with('error', 'Could not connect to authentication service. Please try again later.');
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

        $secret = config('services.lancore.auth_secret');
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

        $roles = is_array($decoded['roles'] ?? null)
            ? array_values(array_filter($decoded['roles'], 'is_string'))
            : [];

        $role = isset($decoded['role']) && is_string($decoded['role'])
            ? UserRole::tryFrom($decoded['role']) ?? UserRole::User
            : $this->resolveRoleFromLanCore($roles);

        $externalId = isset($decoded['user_id']) ? (string) $decoded['user_id'] : null;
        $email = isset($decoded['email']) && is_string($decoded['email'])
            ? strtolower($decoded['email'])
            : ($externalId !== null ? "lancore-user-{$externalId}@users.lancore.local" : null);

        abort_unless($email !== null, 403, 'Invalid payload.');

        $user = $this->upsertLanCoreUserFromArray(
            $externalId,
            $decoded['name'] ?? 'Unknown',
            $email,
            $role,
        );

        Auth::login($user);
        $request->session()->regenerate();

        $redirect = $request->query('redirect', '/');

        return redirect()->to($redirect);
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function resolveRoleFromLanCore(array $roles): UserRole
    {
        return collect($roles)
            ->map(fn (string $role) => UserRole::tryFrom($role))
            ->filter()
            ->sortByDesc(fn (UserRole $role) => match ($role) {
                UserRole::Superadmin => 4,
                UserRole::Admin => 3,
                UserRole::Moderator => 2,
                UserRole::User => 1,
            })
            ->first() ?? UserRole::User;
    }

    private function upsertLanCoreUser(LanCoreUser $lanCoreUser, UserRole $role): User
    {
        return $this->upsertLanCoreUserFromArray(
            (string) $lanCoreUser->id,
            $lanCoreUser->username,
            $lanCoreUser->email,
            $role,
        );
    }

    private function upsertLanCoreUserFromArray(?string $externalId, string $name, ?string $email, UserRole $role): User
    {
        $email ??= $externalId !== null ? "lancore-user-{$externalId}@users.lancore.local" : null;

        abort_unless($email !== null, 403, 'Invalid payload.');

        $user = null;

        if ($externalId !== null) {
            $user = User::query()
                ->where('external_provider', 'lancore')
                ->where('external_id', $externalId)
                ->first();
        }

        if ($user === null) {
            $user = User::query()->where('email', $email)->first();
        }

        $user ??= new User;

        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'role' => $role,
            'external' => true,
            'external_provider' => 'lancore',
            'external_id' => $externalId,
            'password' => $user->exists ? $user->getAuthPassword() : Hash::make(Str::random(40)),
        ])->save();

        return $user;
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
