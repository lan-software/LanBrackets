<?php

namespace App\Domain\Competition\Contracts;

use App\Domain\Competition\DTOs\StandingEntry;
use App\Models\CompetitionStage;

interface StandingsCalculator
{
    /**
     * Calculate standings for a stage.
     *
     * @return array<int, StandingEntry> Ordered by placement (index 0 = 1st place)
     */
    public function calculate(CompetitionStage $stage): array;
}
