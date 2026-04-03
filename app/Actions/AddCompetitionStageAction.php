<?php

namespace App\Actions;

use App\Domain\Competition\Services\FormatRegistry;
use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\CompetitionStage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AddCompetitionStageAction
{
    public function __construct(
        protected FormatRegistry $formatRegistry,
    ) {}

    /**
     * Add a subsequent stage to an existing competition.
     *
     * Sets progression_meta on the previous stage to define how participants
     * qualify into this new stage.
     *
     * @param  array{name?: string, settings?: array<string, mixed>, progression_meta?: array<string, mixed>}  $options
     */
    public function execute(
        Competition $competition,
        StageType $stageType,
        array $options = [],
    ): CompetitionStage {
        if (! $this->formatRegistry->hasFormat($stageType)) {
            throw new InvalidArgumentException(
                "No format implementation registered for stage type [{$stageType->value}]."
            );
        }

        $maxOrder = $competition->stages()->max('order') ?? 0;
        $ruleset = $this->formatRegistry->ruleset($stageType);

        $name = $options['name'] ?? $this->defaultStageName($stageType);

        // Set progression_meta on the previous stage
        if (isset($options['progression_meta'])) {
            $previousStage = $competition->stages()
                ->where('order', $maxOrder)
                ->first();

            if ($previousStage !== null && $previousStage->status === StageStatus::Pending) {
                $previousStage->update([
                    'progression_meta' => array_merge(
                        $previousStage->progression_meta ?? [],
                        $options['progression_meta'],
                    ),
                ]);
            }
        }

        return CompetitionStage::create([
            'competition_id' => $competition->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'order' => $maxOrder + 1,
            'stage_type' => $stageType,
            'status' => StageStatus::Pending,
            'settings' => array_merge(
                $ruleset->defaults(),
                $options['settings'] ?? [],
            ),
        ]);
    }

    protected function defaultStageName(StageType $stageType): string
    {
        return match ($stageType) {
            StageType::SingleElimination => 'Playoffs',
            StageType::DoubleElimination => 'Playoffs',
            StageType::GroupStage => 'Group Stage',
            StageType::Swiss => 'Swiss Rounds',
            StageType::RoundRobin => 'Round Robin',
            StageType::RaceHeat => 'Heats',
            StageType::FinalStage => 'Finals',
        };
    }
}
