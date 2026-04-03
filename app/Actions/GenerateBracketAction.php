<?php

namespace App\Actions;

use App\Domain\Competition\Services\FormatRegistry;
use App\Enums\CompetitionStatus;
use App\Enums\StageStatus;
use App\Events\BracketGenerated;
use App\Models\CompetitionStage;
use InvalidArgumentException;

class GenerateBracketAction
{
    public function __construct(
        protected FormatRegistry $formatRegistry,
    ) {}

    /**
     * Generate matches for a stage using its format generator.
     *
     * Transitions competition to Running and stage to Running status.
     */
    public function execute(CompetitionStage $stage): void
    {
        if ($stage->status !== StageStatus::Pending) {
            throw new InvalidArgumentException(
                "Stage [{$stage->id}] has already been generated (status: {$stage->status->value})."
            );
        }

        $qualifiedIds = $stage->settings['qualified_participant_ids'] ?? null;
        $participantCount = $qualifiedIds !== null
            ? count($qualifiedIds)
            : $stage->competition->participants()->count();
        $minParticipants = $this->minimumParticipants($stage);

        if ($participantCount < $minParticipants) {
            throw new InvalidArgumentException(
                "Stage requires at least {$minParticipants} participants, but only {$participantCount} are registered."
            );
        }

        $generator = $this->formatRegistry->generator($stage->stage_type);
        $generator->generate($stage);

        $stage->update(['status' => StageStatus::Running]);

        if ($stage->competition->status === CompetitionStatus::Draft
            || $stage->competition->status === CompetitionStatus::RegistrationClosed) {
            $stage->competition->update(['status' => CompetitionStatus::Running]);
        }

        event(new BracketGenerated($stage));
    }

    protected function minimumParticipants(CompetitionStage $stage): int
    {
        return match ($stage->stage_type->value) {
            'single_elimination' => 2,
            'double_elimination' => 3,
            'round_robin' => 2,
            'swiss' => 4,
            'group_stage' => 4,
            default => 2,
        };
    }
}
