<?php

namespace App\Domain\Competition\Contracts;

use App\Models\CompetitionMatch;

/**
 * Resolves match outcomes and advances participants through the bracket.
 *
 * After a match is completed, the resolver determines which participant(s)
 * advance and places them into the next match slots via MatchConnections.
 *
 * TODO: Implement resolvers for each StageType
 *   - SingleEliminationResolver
 *   - DoubleEliminationResolver
 *   - SwissResolver (next round pairing)
 *   - RoundRobinResolver
 *   - GroupStageResolver (standings → advancement)
 *   - RaceHeatResolver (times → ranking)
 */
interface FormatResolver
{
    /**
     * Process the result of a completed match and advance participants
     * through connected matches as appropriate.
     */
    public function resolve(CompetitionMatch $match): void;
}
