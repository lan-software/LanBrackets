<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Stable, fully-translated UI locales.
     *
     * @var array<int, string>
     */
    public const STABLE = ['en', 'de', 'fr', 'es'];

    /**
     * Experimental locales — selectable but translation coverage may be incomplete.
     *
     * @var array<int, string>
     */
    public const EXPERIMENTAL = ['sv', 'uk', 'ko', 'tlh', 'nds', 'sxu'];

    /**
     * All available UI locales (stable + experimental). Must match
     * resources/js/locales/*.json filenames.
     *
     * @var array<int, string>
     */
    public const AVAILABLE = [...self::STABLE, ...self::EXPERIMENTAL];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        if (in_array($locale, self::AVAILABLE, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $user = $request->user();

        if ($user && ! empty($user->locale)) {
            return $user->locale;
        }

        $preferred = $request->getPreferredLanguage(self::AVAILABLE);

        if ($preferred) {
            return $preferred;
        }

        return config('app.fallback_locale', 'en');
    }
}
