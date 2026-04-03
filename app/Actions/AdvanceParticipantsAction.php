<?php

namespace App\Actions;

use App\Enums\StageStatus;
use App\Models\CompetitionStage;
use InvalidArgumentException;

class AdvanceParticipantsAction
{
    public function __construct(
        protected GenerateBracketAction $generateBracketAction,
    ) {}

    /**
     * Advance qualified participants from a completed stage to the next stage.
     *
     * Reads qualified_participants from the source stage's progression_meta,
     * stores them as qualified_seeds on the target stage, and generates
     * the target stage's bracket.
     */
    public function execute(CompetitionStage $completedStage, CompetitionStage $nextStage): void
    {
        if ($completedStage->status !== StageStatus::Completed) {
            throw new InvalidArgumentException(
                "Source stage [{$completedStage->id}] must be completed before advancing participants."
            );
        }

        if ($nextStage->status !== StageStatus::Pending) {
            throw new InvalidArgumentException(
                "Target stage [{$nextStage->id}] must be pending to receive advanced participants."
            );
        }

        $qualifiers = $completedStage->progression_meta['qualified_participants'] ?? [];

        if ($qualifiers === []) {
            throw new InvalidArgumentException(
                "No qualified participants found in source stage [{$completedStage->id}]."
            );
        }

        $nextStage->update([
            'settings' => array_merge($nextStage->settings ?? [], [
                'qualified_participant_ids' => array_column($qualifiers, 'participant_id'),
                'qualified_seeds' => $qualifiers,
            ]),
        ]);

        $this->generateBracketAction->execute($nextStage);
    }
}
