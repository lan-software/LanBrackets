<?php

namespace App\Console\Commands;

use App\Actions\CreateCompetitionAction;
use App\Enums\CompetitionType;
use App\Enums\StageType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('competition:create {--name= : Competition name} {--type= : Competition type (tournament, league, race)} {--mode= : Tournament mode / stage type (single_elimination, double_elimination)}')]
#[Description('Create a new competition with an initial stage')]
class CreateCompetition extends Command
{
    public function handle(CreateCompetitionAction $action): int
    {
        $name = $this->option('name') ?? text(
            label: 'Competition name',
            required: true,
        );

        $typeValue = $this->option('type') ?? select(
            label: 'Competition type',
            options: collect(CompetitionType::cases())
                ->mapWithKeys(fn ($case) => [$case->value => ucfirst($case->value)])
                ->toArray(),
        );

        $type = CompetitionType::from($typeValue);

        $availableModes = $this->availableStageTypes($type);

        $modeValue = $this->option('mode') ?? select(
            label: 'Tournament mode',
            options: collect($availableModes)
                ->mapWithKeys(fn ($case) => [$case->value => str_replace('_', ' ', ucfirst($case->value))])
                ->toArray(),
        );

        $stageType = StageType::from($modeValue);

        try {
            $competition = $action->execute($name, $type, $stageType);

            $this->info("Competition created: {$competition->name} (ID: {$competition->id})");
            $this->line("  Type: {$competition->type->value}");
            $this->line("  Stage: {$competition->stages->first()->name} ({$stageType->value})");
            $this->line("  Status: {$competition->status->value}");

            return self::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return StageType[]
     */
    protected function availableStageTypes(CompetitionType $type): array
    {
        return match ($type) {
            CompetitionType::Tournament => [
                StageType::SingleElimination,
                StageType::DoubleElimination,
            ],
            CompetitionType::League => [
                StageType::RoundRobin,
                StageType::Swiss,
                StageType::GroupStage,
            ],
            CompetitionType::Race => [
                StageType::RaceHeat,
                StageType::FinalStage,
            ],
        };
    }
}
