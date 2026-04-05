<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('promotes a user to a privileged role', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $this->artisan('user:promote', [
        'email' => 'admin@example.com',
        '--role' => 'superadmin',
    ])->assertExitCode(0);

    expect($user->fresh()->role)->toBe(UserRole::Superadmin);
});
