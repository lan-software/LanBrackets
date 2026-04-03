<?php

namespace App\Console\Commands;

use App\Actions\AddCompetitionParticipantAction;
use App\Actions\CreateCompetitionAction;
use App\Actions\GenerateBracketAction;
use App\Actions\ReportMatchResultAction;
use App\Enums\CompetitionType;
use App\Enums\MatchStatus;
use App\Enums\StageType;
use App\Models\CompetitionMatch;
use App\Models\Team;
use Illuminate\Console\Command;

class TestFormatFlow extends Command
{
    protected $signature = 'competition:test-flow
        {format : The format to test (single_elimination, double_elimination, round_robin, swiss, group_stage)}
        {--participants=8 : Number of participants}
        {--auto : Auto-resolve all matches with random scores}';

    protected $description = 'Create and optionally play through a test competition for a given format';

    public function handle(
        CreateCompetitionAction $createAction,
        AddCompetitionParticipantAction $addAction,
        GenerateBracketAction $bracketAction,
        ReportMatchResultAction $resultAction,
    ): int {
        $format = $this->argument('format');
        $participantCount = (int) $this->option('participants');
        $auto = $this->option('auto');

        $stageType = StageType::tryFrom($format);
        if ($stageType === null) {
            $this->error("Invalid format: {$format}");
            $this->line('Available: single_elimination, double_elimination, round_robin, swiss, group_stage');

            return self::FAILURE;
        }

        $this->info("Creating {$format} competition with {$participantCount} participants...");

        $competition = $createAction->execute(
            name: "Test {$format} ".now()->format('H:i:s'),
            type: CompetitionType::Tournament,
            stageType: $stageType,
        );

        $this->line("  Competition: #{$competition->id} - {$competition->name}");

        // Add participants
        for ($i = 1; $i <= $participantCount; $i++) {
            $team = Team::create([
                'name' => "Team {$i}",
                'slug' => "test-team-{$i}-".now()->timestamp,
                'tag' => substr("T{$i}", 0, 3),
                'status' => 'active',
            ]);
            $addAction->execute($competition, $team, $i);
        }

        $this->info("  Added {$participantCount} teams.");

        // Generate bracket
        $stage = $competition->stages->first();
        $bracketAction->execute($stage);

        $matchCount = CompetitionMatch::where('competition_stage_id', $stage->id)->count();
        $this->info("  Generated bracket: {$matchCount} matches.");

        if (! $auto) {
            $this->info('Bracket generated. Use --auto to auto-play all matches.');

            return self::SUCCESS;
        }

        // Auto-play matches
        $this->info('  Auto-playing matches...');
        $maxRounds = 50;

        for ($round = 0; $round < $maxRounds; $round++) {
            $pendingMatches = CompetitionMatch::query()
                ->where('competition_stage_id', $stage->id)
                ->where('status', MatchStatus::Pending)
                ->whereHas('matchParticipants', function ($q) {
                    $q->havingRaw('COUNT(*) = 2');
                }, '>=', 2)
                ->get();

            if ($pendingMatches->isEmpty()) {
                break;
            }

            foreach ($pendingMatches as $match) {
                $mps = $match->matchParticipants()->get();
                if ($mps->count() !== 2) {
                    continue;
                }

                $score1 = random_int(0, 10);
                $score2 = random_int(0, 10);

                // Avoid ties for formats that don't allow draws
                $allowDraws = $stage->settings['allow_draws'] ?? false;
                if (! $allowDraws && $score1 === $score2) {
                    $score1++;
                }

                $resultAction->execute($match, [1 => $score1, 2 => $score2]);

                $match->refresh();
                $winner = $match->winner_participant_id ? 'Winner: P'.$match->winner_participant_id : 'Draw';
                $this->line("    R{$match->round_number} M{$match->sequence}: {$score1}-{$score2} ({$winner})");
            }
        }

        $finished = CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('status', MatchStatus::Finished)
            ->count();
        $total = CompetitionMatch::where('competition_stage_id', $stage->id)->count();

        $this->newLine();
        $this->info("  Completed: {$finished}/{$total} matches finished.");

        $pending = CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('status', MatchStatus::Pending)
            ->count();

        if ($pending > 0) {
            $this->warn("  {$pending} matches still pending (may need more participants to advance).");
        } else {
            $this->info('  All matches completed successfully!');
        }

        return self::SUCCESS;
    }
}
