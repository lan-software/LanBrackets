<?php

namespace App\Console\Commands;

use App\Actions\GenerateBracketAction;
use App\Models\Competition;
use App\Models\CompetitionStage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;

#[Signature('competition:generate-bracket {competition : The competition ID} {--stage= : Stage ID (auto-detects if only one stage)}')]
#[Description('Generate the bracket/matches for a competition stage')]
class GenerateBracket extends Command
{
    public function handle(GenerateBracketAction $action): int
    {
        $competition = Competition::with('stages', 'participants')->find($this->argument('competition'));

        if ($competition === null) {
            $this->error('Competition not found.');

            return self::FAILURE;
        }

        $this->info("Competition: {$competition->name}");
        $this->line("  Participants: {$competition->participants->count()}");

        $stage = $this->resolveStage($competition);

        if ($stage === null) {
            return self::FAILURE;
        }

        $this->line("  Stage: {$stage->name} ({$stage->stage_type->value})");

        try {
            $action->execute($stage);

            $matchCount = $stage->matches()->count();
            $this->info("Bracket generated: {$matchCount} matches created.");

            return self::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function resolveStage(Competition $competition): ?CompetitionStage
    {
        if ($this->option('stage')) {
            $stage = $competition->stages->firstWhere('id', (int) $this->option('stage'));

            if ($stage === null) {
                $this->error('Stage not found in this competition.');

                return null;
            }

            return $stage;
        }

        if ($competition->stages->count() === 1) {
            return $competition->stages->first();
        }

        if ($competition->stages->isEmpty()) {
            $this->error('No stages defined for this competition.');

            return null;
        }

        $stageId = select(
            label: 'Select a stage',
            options: $competition->stages
                ->mapWithKeys(fn ($s) => [$s->id => "{$s->name} ({$s->stage_type->value}) - {$s->status->value}"])
                ->toArray(),
        );

        return $competition->stages->firstWhere('id', (int) $stageId);
    }
}
