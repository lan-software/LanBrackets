<?php

namespace App\Domain\Competition\Concerns;

use App\Actions\CompleteStageAction;
use App\Models\CompetitionMatch;

trait DetectsStageCompletion
{
    /**
     * Check if the stage is now complete after resolving a match.
     *
     * If all matches are finished, triggers stage completion and advancement.
     */
    protected function checkStageCompletion(CompetitionMatch $match): void
    {
        $stage = $match->stage;

        if ($stage->isComplete()) {
            app(CompleteStageAction::class)->execute($stage);
        }
    }
}
