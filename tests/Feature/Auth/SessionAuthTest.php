<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the login page', function () {
    $this->get(route('login'))
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

it('redirects guests to login when they access the web ui', function () {
    $this->get(route('home'))
        ->assertRedirect(route('login'));
});

it('allows authenticated users without a privileged role to view the welcome page', function () {
    $user = User::factory()->withRole(UserRole::User)->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertSuccessful();
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
