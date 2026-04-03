<?php

namespace App\Actions;

use App\Domain\Competition\DTOs\StandingEntry;
use App\Domain\Competition\Services\FormatRegistry;
use App\Enums\CompetitionStatus;
use App\Enums\StageStatus;
use App\Models\Competition;
use App\Models\CompetitionStage;
use InvalidArgumentException;

class CompleteStageAction
{
    public function __construct(
        protected FormatRegistry $formatRegistry,
        protected AdvanceParticipantsAction $advanceAction,
    ) {}

    /**
     * Mark a stage as completed, calculate standings, and advance participants.
     */
    public function execute(CompetitionStage $stage): void
    {
        if ($stage->status === StageStatus::Completed) {
            return;
        }

        if (! $stage->isComplete()) {
            throw new InvalidArgumentException(
                "Stage [{$stage->id}] is not complete — not all matches are finished."
            );
        }

        $calculator = $this->formatRegistry->standingsCalculator($stage->stage_type);
        $standings = $calculator->calculate($stage);

        $qualifiers = $this->determineQualifiers($stage, $standings);

        $stage->update([
            'status' => StageStatus::Completed,
            'progression_meta' => array_merge($stage->progression_meta ?? [], [
                'standings' => array_map(fn (StandingEntry $s) => $s->toArray(), $standings),
                'qualified_participants' => $qualifiers,
            ]),
        ]);

        $nextStage = $this->getNextStage($stage);

        if ($nextStage !== null && $qualifiers !== []) {
            $this->advanceAction->execute($stage, $nextStage);
        }

        if ($nextStage === null) {
            $this->checkCompetitionCompletion($stage->competition);
        }
    }

    /**
     * Determine which participants qualify for the next stage.
     *
     * @param  array<int, StandingEntry>  $standings
     * @return array<int, array{participant_id: int, new_seed: int}>
     */
    protected function determineQualifiers(CompetitionStage $stage, array $standings): array
    {
        $meta = $stage->progression_meta ?? [];
        $advanceCount = $meta['advance_count'] ?? null;
        $perGroup = $meta['per_group'] ?? null;

        if ($advanceCount === null && $perGroup === null) {
            return [];
        }

        if ($perGroup !== null) {
            return $this->qualifyPerGroup($standings, $perGroup);
        }

        return collect($standings)
            ->take($advanceCount)
            ->map(fn (StandingEntry $s, int $i) => [
                'participant_id' => $s->participantId,
                'new_seed' => $i + 1,
            ])
            ->values()
            ->all();
    }

    /**
     * Qualify top N per group, maintaining cross-group interleaved seeding.
     *
     * Standings are already interleaved (all 1st-place, then all 2nd-place, etc.)
     * so we just take entries whose within-group placement is <= perGroup.
     *
     * @param  array<int, StandingEntry>  $standings
     * @return array<int, array{participant_id: int, new_seed: int}>
     */
    protected function qualifyPerGroup(array $standings, int $perGroup): array
    {
        // Track how many we've taken from each group
        $groupCounts = [];

        $qualifiers = [];
        $seed = 1;

        foreach ($standings as $entry) {
            $groupId = $entry->groupId;
            $groupCounts[$groupId] = ($groupCounts[$groupId] ?? 0) + 1;

            if ($groupCounts[$groupId] <= $perGroup) {
                $qualifiers[] = [
                    'participant_id' => $entry->participantId,
                    'new_seed' => $seed++,
                ];
            }
        }

        return $qualifiers;
    }

    protected function getNextStage(CompetitionStage $stage): ?CompetitionStage
    {
        return CompetitionStage::where('competition_id', $stage->competition_id)
            ->where('order', '>', $stage->order)
            ->orderBy('order')
            ->first();
    }

    protected function checkCompetitionCompletion(Competition $competition): void
    {
        $allComplete = $competition->stages()
            ->where('status', '!=', StageStatus::Completed)
            ->doesntExist();

        if ($allComplete) {
            $competition->update(['status' => CompetitionStatus::Finished]);
        }
    }
}
