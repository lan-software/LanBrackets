<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the login page', function () {
    // LanCore is enabled by default in .env, so /login redirects to SSO.
    // ?local=1 forces the native login form to render.
    $this->get(route('login', ['local' => '']))
        ->assertSuccessful();
});

it('authenticates privileged users with the login form', function () {
    $user = User::factory()->withRole(UserRole::Admin)->create([
        'password' => 'password',
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('serves the landing page to guests without a redirect', function () {
    // / is a public landing page — guests see it directly.
    $this->get(route('home'))
        ->assertSuccessful();
});

it('forwards authenticated users from the landing page to the dashboard', function () {
    $user = User::factory()->withRole(UserRole::User)->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});

it('returns forbidden for authenticated users without a privileged role on competitions', function () {
    $user = User::factory()->withRole(UserRole::User)->create();

    $this->actingAs($user)
        ->get(route('competitions.index'))
        ->assertForbidden();
});

it('logs users out with the standard session route', function () {
    $user = User::factory()->withRole(UserRole::Admin)->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
