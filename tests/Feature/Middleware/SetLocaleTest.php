<?php

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Services\UserSyncService;
use LanSoftware\LanCoreClient\DTOs\LanCoreUser;

test('set locale middleware applies the user locale', function () {
    $user = User::factory()->create(['locale' => 'fr']);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect(app()->getLocale())->toBe('fr');
});

test('set locale middleware falls back when user has no locale', function () {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect(app()->getLocale())->toBe(config('app.fallback_locale'));
});

test('inertia response exposes locale, availableLocales, and experimentalLocales', function () {
    $user = User::factory()->create(['locale' => 'es']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'es')
            ->where('availableLocales', SetLocale::AVAILABLE)
            ->where('experimentalLocales', SetLocale::EXPERIMENTAL)
            ->etc()
        );
});

test('user sync persists locale from LanCore', function () {
    $service = app(UserSyncService::class);

    $dto = new LanCoreUser(
        id: 42,
        username: 'synced-user',
        email: 'synced@example.com',
        roles: [],
        locale: 'fr',
    );

    $user = $service->resolveFromLanCore($dto);

    expect($user->locale)->toBe('fr');
});

test('locale sets are partitioned correctly', function () {
    expect(SetLocale::STABLE)->toBe(['en', 'de', 'fr', 'es'])
        ->and(SetLocale::EXPERIMENTAL)->toBe(['sv', 'uk', 'ko', 'tlh', 'nds', 'sxu'])
        ->and(SetLocale::AVAILABLE)->toBe([...SetLocale::STABLE, ...SetLocale::EXPERIMENTAL])
        ->and(array_intersect(SetLocale::STABLE, SetLocale::EXPERIMENTAL))->toBe([]);
});
