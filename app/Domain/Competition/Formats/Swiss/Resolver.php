<?php

namespace App\Domain\Competition\Formats\Swiss;

use App\Domain\Competition\Concerns\DetectsStageCompletion;
use App\Domain\Competition\Contracts\FormatResolver;
use App\Enums\MatchResult;
use App\Enums\MatchStatus;
use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionStage;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class Resolver implements FormatResolver
{
    use DetectsStageCompletion;

    /**
     * Resolve a Swiss match and generate the next round if all current-round matches are done.
     */
    public function resolve(CompetitionMatch $match): void
    {
        $this->resolveMatch($match);

        $stage = $match->stage;
        $currentRound = $match->round_number;
        $totalRounds = $stage->settings['total_rounds'] ?? 3;

        if ($currentRound >= $totalRounds) {
            $this->checkStageCompletion($match);

            return;
        }

        // Check if all matches in the current round are finished
        $pendingInRound = CompetitionMatch::query()
            ->where('competition_stage_id', $stage->id)
            ->where('round_number', $currentRound)
            ->where('status', '!=', MatchStatus::Finished)
            ->count();

        if ($pendingInRound > 0) {
            return;
        }

        $this->generateNextRound($stage, $currentRound + 1);
    }

    /**
     * Determine the winner of a single match.
     */
    protected function resolveMatch(CompetitionMatch $match): void
    {
        $participants = $match->matchParticipants()->get();

        if ($participants->count() !== 2) {
            throw new InvalidArgumentException(
                "Match [{$match->id}] must have exactly 2 participants to resolve."
            );
        }

        $p1 = $participants->firstWhere('slot', 1);
        $p2 = $participants->firstWhere('slot', 2);

        if ($p1 === null || $p2 === null) {
            throw new InvalidArgumentException(
                "Match [{$match->id}] is missing a participant in slot 1 or 2."
            );
        }

        if ($p1->score === null || $p2->score === null) {
            throw new InvalidArgumentException(
                "Match [{$match->id}] cannot be resolved: scores are not set."
            );
        }

        if ($p1->score === $p2->score) {
            throw new InvalidArgumentException(
                "Match [{$match->id}] cannot be resolved: scores are tied. "
                .'Swiss format does not allow draws.'
            );
        }

        [$winner, $loser] = $p1->score > $p2->score
            ? [$p1, $p2]
            : [$p2, $p1];

        $winner->update(['result' => MatchResult::Win]);
        $loser->update(['result' => MatchResult::Loss]);

        $match->update([
            'status' => MatchStatus::Finished,
            'winner_participant_id' => $winner->competition_participant_id,
            'loser_participant_id' => $loser->competition_participant_id,
            'finished_at' => now(),
        ]);
    }

    /**
     * Generate the next round's pairings based on current standings.
     */
    protected function generateNextRound(CompetitionStage $stage, int $roundNumber): void
    {
        $participants = $stage->competition
            ->participants()
            ->whereNull('metadata->disqualified')
            ->get();

        $standings = $this->calculateStandings($stage, $participants);
        $paired = $this->pairByStandings($stage, $standings, $participants);

        app(Generator::class)->generateRound($stage, $paired, $roundNumber);
    }

    /**
     * Calculate current Swiss standings.
     *
     * @return array<int, array{wins: int, buchholz: float, participant_id: int}>
     */
    protected function calculateStandings(
        CompetitionStage $stage,
        Collection $participants,
    ): array {
        $allMatches = CompetitionMatch::query()
            ->where('competition_stage_id', $stage->id)
            ->where('status', MatchStatus::Finished)
            ->get();

        // Calculate wins per participant
        $wins = [];
        $opponents = [];

        foreach ($participants as $p) {
            $wins[$p->id] = 0;
            $opponents[$p->id] = [];
        }

        foreach ($allMatches as $match) {
            $mps = $match->matchParticipants;

            if ($mps->count() < 2) {
                // BYE match — count as a win but no opponent
                if ($match->winner_participant_id) {
                    $wins[$match->winner_participant_id] = ($wins[$match->winner_participant_id] ?? 0) + 1;
                }

                continue;
            }

            if ($match->winner_participant_id) {
                $wins[$match->winner_participant_id] = ($wins[$match->winner_participant_id] ?? 0) + 1;
            }

            $ids = $mps->pluck('competition_participant_id')->all();
            if (count($ids) === 2) {
                $opponents[$ids[0]][] = $ids[1];
                $opponents[$ids[1]][] = $ids[0];
            }
        }

        // Calculate Buchholz tiebreaker (sum of opponents' wins)
        $standings = [];
        foreach ($participants as $p) {
            $buchholz = 0;
            foreach ($opponents[$p->id] ?? [] as $oppId) {
                $buchholz += $wins[$oppId] ?? 0;
            }

            $standings[] = [
                'participant_id' => $p->id,
                'wins' => $wins[$p->id] ?? 0,
                'buchholz' => $buchholz,
            ];
        }

        // Sort by wins desc, then buchholz desc
        usort($standings, function ($a, $b) {
            if ($a['wins'] !== $b['wins']) {
                return $b['wins'] - $a['wins'];
            }

            return $b['buchholz'] <=> $a['buchholz'];
        });

        return $standings;
    }

    /**
     * Pair participants by standings, avoiding repeat matchups.
     *
     * @param  array<int, array{wins: int, buchholz: float, participant_id: int}>  $standings
     * @return array<int, CompetitionParticipant>
     */
    protected function pairByStandings(
        CompetitionStage $stage,
        array $standings,
        Collection $participants,
    ): array {
        // Collect all previous matchups
        $previousMatchups = $this->getPreviousMatchups($stage);

        // Collect BYE history
        $byeHistory = $this->getByeHistory($stage);

        $remaining = array_map(fn ($s) => $s['participant_id'], $standings);
        $paired = [];

        while (count($remaining) > 1) {
            $current = array_shift($remaining);

            // Find the first opponent in remaining that hasn't been played
            $opponentIndex = null;
            foreach ($remaining as $idx => $candidate) {
                $matchupKey = min($current, $candidate).'-'.max($current, $candidate);
                if (! isset($previousMatchups[$matchupKey])) {
                    $opponentIndex = $idx;
                    break;
                }
            }

            // Fallback: if all have been played, pick the first available
            if ($opponentIndex === null) {
                $opponentIndex = array_key_first($remaining);
            }

            $opponent = $remaining[$opponentIndex];
            unset($remaining[$opponentIndex]);
            $remaining = array_values($remaining);

            $paired[] = $participants->firstWhere('id', $current);
            $paired[] = $participants->firstWhere('id', $opponent);
        }

        // Odd one out gets BYE — prefer participant who hasn't had one
        if (count($remaining) === 1) {
            $byeCandidate = reset($remaining);

            // If this participant already had a BYE, try to swap with the last paired participant
            if (isset($byeHistory[$byeCandidate]) && count($paired) >= 2) {
                $lastPaired = $paired[count($paired) - 1];
                if (! isset($byeHistory[$lastPaired->id])) {
                    // Swap: last paired gets BYE, byeCandidate takes their spot
                    $paired[count($paired) - 1] = $participants->firstWhere('id', $byeCandidate);
                    $paired[] = $lastPaired;

                    return $paired;
                }
            }

            $paired[] = $participants->firstWhere('id', $byeCandidate);
        }

        return $paired;
    }

    /**
     * Get all previous matchups as a set of "min-max" keys.
     *
     * @return array<string, true>
     */
    protected function getPreviousMatchups(CompetitionStage $stage): array
    {
        $matches = CompetitionMatch::query()
            ->where('competition_stage_id', $stage->id)
            ->with('matchParticipants')
            ->get();

        $matchups = [];
        foreach ($matches as $match) {
            $ids = $match->matchParticipants->pluck('competition_participant_id')->all();
            if (count($ids) === 2) {
                $key = min($ids[0], $ids[1]).'-'.max($ids[0], $ids[1]);
                $matchups[$key] = true;
            }
        }

        return $matchups;
    }

    /**
     * Get which participants have already received a BYE.
     *
     * @return array<int, true>
     */
    protected function getByeHistory(CompetitionStage $stage): array
    {
        $byeMatches = CompetitionMatch::query()
            ->where('competition_stage_id', $stage->id)
            ->where('status', MatchStatus::Finished)
            ->whereHas('matchParticipants', function ($q) {
                $q->where('result', MatchResult::Bye);
            })
            ->get();

        $history = [];
        foreach ($byeMatches as $match) {
            if ($match->winner_participant_id) {
                $history[$match->winner_participant_id] = true;
            }
        }

        return $history;
    }
}
