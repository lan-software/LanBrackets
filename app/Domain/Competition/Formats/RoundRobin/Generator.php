<?php

namespace App\Domain\Competition\Formats\RoundRobin;

use App\Domain\Competition\Contracts\FormatGenerator;
use App\Models\CompetitionStage;
use InvalidArgumentException;

class Generator implements FormatGenerator
{
    public function __construct(
        protected Scheduler $scheduler,
    ) {}

    /**
     * Generate a complete round-robin schedule for all participants in the stage.
     *
     * Creates all matches upfront using the circle method. Every participant
     * plays every other participant exactly once.
     */
    public function generate(CompetitionStage $stage): void
    {
        $participants = $stage->getStageParticipants();

        if ($participants->count() < 2) {
            throw new InvalidArgumentException(
                'Round robin requires at least 2 participants.'
            );
        }

        $this->scheduler->schedule(
            participants: $participants,
            competitionId: $stage->competition_id,
            stageId: $stage->id,
        );
    }
}
