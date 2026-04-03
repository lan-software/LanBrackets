<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private const ALLOWED_ROLES = ['moderator', 'admin', 'superadmin'];

    public function callback(Request $request): RedirectResponse
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
            isset($decoded['role']) && in_array($decoded['role'], self::ALLOWED_ROLES, true),
            403,
            'Insufficient permissions. Required role: moderator, admin, or superadmin.',
        );

        $request->session()->put('lancore_user', [
            'user_id' => $decoded['user_id'] ?? null,
            'name' => $decoded['name'] ?? 'Unknown',
            'role' => $decoded['role'],
            'external' => true,
        ]);

        $redirect = $request->query('redirect', '/');

        return redirect()->to($redirect);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('lancore_user');

        return redirect()->route('home');
    }
}
