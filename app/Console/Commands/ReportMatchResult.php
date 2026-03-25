<?php

namespace App\Console\Commands;

use App\Actions\ReportMatchResultAction;
use App\Enums\MatchStatus;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('competition:report-result {competition : The competition ID} {--match= : Match ID} {--score1= : Score for slot 1} {--score2= : Score for slot 2}')]
#[Description('Report scores for a match and resolve it')]
class ReportMatchResult extends Command
{
    public function handle(ReportMatchResultAction $action): int
    {
        $competition = Competition::find($this->argument('competition'));

        if ($competition === null) {
            $this->error('Competition not found.');

            return self::FAILURE;
        }

        $match = $this->resolveMatch($competition);

        if ($match === null) {
            return self::FAILURE;
        }

        $match->load('matchParticipants.competitionParticipant.participant');

        $p1 = $match->matchParticipants->firstWhere('slot', 1);
        $p2 = $match->matchParticipants->firstWhere('slot', 2);

        $name1 = $p1?->competitionParticipant?->participant?->name ?? 'Slot 1';
        $name2 = $p2?->competitionParticipant?->participant?->name ?? 'Slot 2';

        $this->info("Match #{$match->id}: {$name1} vs {$name2}");

        $score1 = $this->option('score1') ?? text(
            label: "Score for {$name1} (slot 1)",
            required: true,
            validate: fn (string $value) => is_numeric($value) ? null : 'Score must be a number.',
        );

        $score2 = $this->option('score2') ?? text(
            label: "Score for {$name2} (slot 2)",
            required: true,
            validate: fn (string $value) => is_numeric($value) ? null : 'Score must be a number.',
        );

        try {
            $action->execute($match, [
                1 => (int) $score1,
                2 => (int) $score2,
            ]);

            $match->refresh();
            $winnerName = $match->winner?->participant?->name ?? 'Unknown';
            $this->info("Result recorded. Winner: {$winnerName}");

            return self::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function resolveMatch(Competition $competition): ?CompetitionMatch
    {
        if ($this->option('match')) {
            $match = CompetitionMatch::where('competition_id', $competition->id)
                ->find((int) $this->option('match'));

            if ($match === null) {
                $this->error('Match not found in this competition.');

                return null;
            }

            return $match;
        }

        $readyMatches = CompetitionMatch::where('competition_id', $competition->id)
            ->where('status', MatchStatus::Pending)
            ->withCount('matchParticipants')
            ->with('matchParticipants.competitionParticipant.participant')
            ->orderBy('round_number')
            ->orderBy('sequence')
            ->get()
            ->where('match_participants_count', 2);

        if ($readyMatches->isEmpty()) {
            $this->warn('No matches are ready to be played.');

            return null;
        }

        $matchId = select(
            label: 'Select a match to report',
            options: $readyMatches->mapWithKeys(function ($m) {
                $p1 = $m->matchParticipants->firstWhere('slot', 1);
                $p2 = $m->matchParticipants->firstWhere('slot', 2);
                $n1 = $p1?->competitionParticipant?->participant?->name ?? '?';
                $n2 = $p2?->competitionParticipant?->participant?->name ?? '?';
                $bracket = $m->settings['bracket_side'] ?? '';
                $label = "R{$m->round_number} M{$m->sequence}: {$n1} vs {$n2}";
                if ($bracket) {
                    $label .= " [{$bracket}]";
                }

                return [$m->id => $label];
            })->toArray(),
        );

        return CompetitionMatch::find((int) $matchId);
    }
}
