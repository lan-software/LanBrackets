<?php

namespace App\Domain\Competition\Formats\SingleElimination;

use App\Domain\Competition\Contracts\StandingsCalculator as StandingsCalculatorContract;
use App\Domain\Competition\DTOs\StandingEntry;
use App\Enums\MatchStatus;
use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionStage;
use Illuminate\Database\Eloquent\Collection;

class StandingsCalculator implements StandingsCalculatorContract
{
    /**
     * Calculate single elimination standings by round eliminated.
     *
     * Winner of final = 1st, loser of final = 2nd,
     * semifinal losers = 3rd/4th (tiebroken by seed), etc.
     *
     * @return array<int, StandingEntry>
     */
    public function calculate(CompetitionStage $stage): array
    {
        $maxRound = CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('status', MatchStatus::Finished)
            ->max('round_number');

        if ($maxRound === null) {
            return [];
        }

        $participants = $stage->competition->participants()
            ->whereNull('metadata->disqualified')
            ->get()
            ->keyBy('id');

        $entries = [];

        // Final winner = 1st
        $final = CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', $maxRound)
            ->where('sequence', 1)
            ->where('status', MatchStatus::Finished)
            ->first();

        if ($final === null) {
            return [];
        }

        if ($final->winner_participant_id) {
            $entries[] = $this->makeEntry($final->winner_participant_id, 1, $participants);
        }

        if ($final->loser_participant_id) {
            $entries[] = $this->makeEntry($final->loser_participant_id, 2, $participants);
        }

        // Walk backwards through rounds: losers of each round share a placement tier
        $placement = 3;
        for ($round = $maxRound - 1; $round >= 1; $round--) {
            $roundLosers = CompetitionMatch::where('competition_stage_id', $stage->id)
                ->where('round_number', $round)
                ->where('status', MatchStatus::Finished)
                ->whereNotNull('loser_participant_id')
                ->where(function ($q) {
                    // Exclude 3rd place match (settings->third_place = true)
                    $q->whereNull('settings->third_place')
                        ->orWhere('settings->third_place', false);
                })
                ->orderBy('sequence')
                ->pluck('loser_participant_id');

            // Sort by original seed within the tier
            $sorted = $roundLosers->sort(function ($a, $b) use ($participants) {
                $seedA = $participants[$a]->seed ?? PHP_INT_MAX;
                $seedB = $participants[$b]->seed ?? PHP_INT_MAX;

                return $seedA - $seedB;
            });

            foreach ($sorted as $loserId) {
                $entries[] = $this->makeEntry($loserId, $placement, $participants);
            }

            $placement += $roundLosers->count();
        }

        return $entries;
    }

    /**
     * @param  Collection<int, CompetitionParticipant>  $participants
     */
    protected function makeEntry(int $participantId, int $placement, $participants): StandingEntry
    {
        return new StandingEntry(
            participantId: $participantId,
            placement: $placement,
            wins: 0,
            losses: 0,
            draws: 0,
            points: 0,
            tiebreaker: (float) ($participants[$participantId]->seed ?? 0),
        );
    }
}
