<?php

namespace App\Domain\Competition\Contracts;

use App\Models\CompetitionStage;

/**
 * Generates the initial bracket/match structure for a competition stage.
 *
 * Implementations will create matches, match connections, and group assignments
 * based on the stage type and its participants.
 *
 * TODO: Implement generators for each StageType
 *   - SingleEliminationGenerator
 *   - DoubleEliminationGenerator
 *   - SwissGenerator
 *   - RoundRobinGenerator
 *   - GroupStageGenerator
 *   - RaceHeatGenerator
 */
interface FormatGenerator
{
    /**
     * Generate the match structure for the given stage.
     * Should create all matches, match connections, and initial seeding.
     */
    public function generate(CompetitionStage $stage): void;
}
