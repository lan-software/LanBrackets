<?php

namespace App\Actions;

use App\Domain\Competition\Services\FormatRegistry;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Enums\CompetitionVisibility;
use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\CompetitionStage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateCompetitionAction
{
    public function __construct(
        protected FormatRegistry $formatRegistry,
    ) {}

    /**
     * Create a competition with its initial stage.
     *
     * @param  array{description?: string, visibility?: CompetitionVisibility, settings?: array<string, mixed>}  $options
     */
    public function execute(
        string $name,
        CompetitionType $type,
        StageType $stageType,
        array $options = [],
    ): Competition {
        if ($type !== CompetitionType::Tournament) {
            if (in_array($stageType, [StageType::SingleElimination, StageType::DoubleElimination])) {
                throw new InvalidArgumentException(
                    "Stage type [{$stageType->value}] is only available for tournament competitions."
                );
            }
        }

        if (! $this->formatRegistry->hasFormat($stageType)) {
            throw new InvalidArgumentException(
                "No format implementation registered for stage type [{$stageType->value}]."
            );
        }

        $competition = Competition::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $options['description'] ?? null,
            'type' => $type,
            'status' => CompetitionStatus::Draft,
            'visibility' => $options['visibility'] ?? CompetitionVisibility::Private,
            'settings' => $options['settings'] ?? [],
        ]);

        $ruleset = $this->formatRegistry->ruleset($stageType);

        CompetitionStage::create([
            'competition_id' => $competition->id,
            'name' => $this->defaultStageName($stageType),
            'slug' => Str::slug($this->defaultStageName($stageType)),
            'order' => 1,
            'stage_type' => $stageType,
            'status' => StageStatus::Pending,
            'settings' => $ruleset->defaults(),
        ]);

        return $competition->load('stages');
    }

    protected function defaultStageName(StageType $stageType): string
    {
        return match ($stageType) {
            StageType::SingleElimination => 'Main Bracket',
            StageType::DoubleElimination => 'Main Bracket',
            StageType::GroupStage => 'Group Stage',
            StageType::Swiss => 'Swiss Rounds',
            StageType::RoundRobin => 'Round Robin',
            StageType::RaceHeat => 'Heats',
            StageType::FinalStage => 'Finals',
        };
    }
}
