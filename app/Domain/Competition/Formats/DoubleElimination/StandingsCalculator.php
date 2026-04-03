<?php

namespace App\Domain\Competition\Formats\DoubleElimination;

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
     * Calculate double elimination standings.
     *
     * GF/GF Reset winner = 1st, GF loser = 2nd,
     * then losers bracket losers by round eliminated (deeper = higher placement).
     *
     * @return array<int, StandingEntry>
     */
    public function calculate(CompetitionStage $stage): array
    {
        $participants = $stage->competition->participants()
            ->whereNull('metadata->disqualified')
            ->get()
            ->keyBy('id');

        $entries = [];
        $placed = [];

        // Grand Final Reset (202) or Grand Final (200) determines 1st/2nd
        $gfReset = CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 202)
            ->where('status', MatchStatus::Finished)
            ->first();

        $gf = CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', 200)
            ->where('status', MatchStatus::Finished)
            ->first();

        $decidingMatch = $gfReset ?? $gf;

        if ($decidingMatch === null) {
            return [];
        }

        if ($decidingMatch->winner_participant_id) {
            $entries[] = $this->makeEntry($decidingMatch->winner_participant_id, 1, $participants);
            $placed[$decidingMatch->winner_participant_id] = true;
        }

        if ($decidingMatch->loser_participant_id) {
            $entries[] = $this->makeEntry($decidingMatch->loser_participant_id, 2, $participants);
            $placed[$decidingMatch->loser_participant_id] = true;
        }

        // LB losers: higher LB round = better placement
        $lbMatches = CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', '>=', 100)
            ->where('round_number', '<', 200)
            ->where('status', MatchStatus::Finished)
            ->whereNotNull('loser_participant_id')
            ->orderByDesc('round_number')
            ->orderBy('sequence')
            ->get();

        $placement = 3;
        $currentRound = null;
        $roundLosers = [];

        foreach ($lbMatches as $match) {
            if ($currentRound !== null && $match->round_number !== $currentRound) {
                // Flush previous round's losers
                $placement = $this->flushRoundLosers($roundLosers, $placement, $participants, $placed, $entries);
                $roundLosers = [];
            }
            $currentRound = $match->round_number;
            $roundLosers[] = $match->loser_participant_id;
        }

        // Flush last round
        if ($roundLosers !== []) {
            $placement = $this->flushRoundLosers($roundLosers, $placement, $participants, $placed, $entries);
        }

        // WB R1 losers who lost in LB R1 are already captured above.
        // Any participant not yet placed (shouldn't happen in a complete tournament).

        return $entries;
    }

    /**
     * @param  array<int>  $loserIds
     * @param  array<int, true>  $placed
     * @param  array<int, StandingEntry>  $entries
     */
    protected function flushRoundLosers(
        array $loserIds,
        int $placement,
        mixed $participants,
        array &$placed,
        array &$entries,
    ): int {
        $filtered = array_filter($loserIds, fn ($id) => ! isset($placed[$id]));

        // Sort by seed within tier
        usort($filtered, function ($a, $b) use ($participants) {
            $seedA = $participants[$a]->seed ?? PHP_INT_MAX;
            $seedB = $participants[$b]->seed ?? PHP_INT_MAX;

            return $seedA - $seedB;
        });

        foreach ($filtered as $id) {
            $entries[] = $this->makeEntry($id, $placement, $participants);
            $placed[$id] = true;
        }

        return $placement + count($filtered);
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
