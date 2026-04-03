<?php

namespace App\Domain\Competition\Formats\GroupStage;

use App\Domain\Competition\Contracts\FormatResolver;
use App\Domain\Competition\Formats\RoundRobin\Resolver as RoundRobinResolver;
use App\Models\CompetitionMatch;

class Resolver implements FormatResolver
{
    /**
     * Resolve a group stage match.
     *
     * Delegates to the RoundRobin resolver since group stage matches
     * follow the same rules (supports draws, no bracket advancement).
     */
    public function resolve(CompetitionMatch $match): void
    {
        (new RoundRobinResolver)->resolve($match);
    }
}
