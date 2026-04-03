<?php

namespace App\Providers;

use App\Events\BracketGenerated;
use App\Events\CompetitionCompleted;
use App\Events\MatchResultReported;
use App\Events\StageCompleted;
use App\Listeners\DispatchWebhook;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureEvents();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register event listeners for webhook dispatch.
     */
    protected function configureEvents(): void
    {
        Event::listen(BracketGenerated::class, [DispatchWebhook::class, 'handleBracketGenerated']);
        Event::listen(MatchResultReported::class, [DispatchWebhook::class, 'handleMatchResultReported']);
        Event::listen(StageCompleted::class, [DispatchWebhook::class, 'handleStageCompleted']);
        Event::listen(CompetitionCompleted::class, [DispatchWebhook::class, 'handleCompetitionCompleted']);
    }

    /**
     * Register the polymorphic morph map for competition participants.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'team' => Team::class,
            'user' => User::class,
        ]);
    }
}
