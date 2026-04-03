<?php

namespace App\Domain\Competition\Formats\DoubleElimination;

use App\Domain\Competition\Contracts\FormatGenerator;
use App\Enums\MatchStatus;
use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionStage;
use App\Models\MatchConnection;
use App\Models\MatchParticipant;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Generates a double-elimination bracket.
 *
 * Structure:
 * - Winners Bracket (WB): standard single-elimination bracket
 * - Losers Bracket (LB): losers from WB drop down; LB has roughly 2x rounds
 * - Grand Final (GF): WB champion vs LB champion
 *
 * Match metadata uses settings JSON to store bracket_side ('winners', 'losers', 'grand_final').
 */
class Generator implements FormatGenerator
{
    public function generate(CompetitionStage $stage): void
    {
        $participants = $stage->competition
            ->participants()
            ->whereNull('metadata->disqualified')
            ->orderBy('seed')
            ->get();

        $count = $participants->count();

        if ($count < 3) {
            throw new InvalidArgumentException(
                'Double elimination requires at least 3 participants.'
            );
        }

        $wbRounds = (int) ceil(log($count, 2));
        $bracketSize = (int) pow(2, $wbRounds);
        $seeded = $this->seedParticipants($participants, $bracketSize);

        // ── Winners Bracket ──
        $wbMatches = $this->generateWinnersBracket($stage, $seeded, $bracketSize, $wbRounds);

        // ── Losers Bracket ──
        $lbMatches = $this->generateLosersBracket($stage, $wbMatches, $wbRounds);

        // Advance WB BYE winners through winner connections now that rounds are wired
        $this->advanceByeWinners($stage);

        // ── Grand Final ──
        $grandFinal = $this->generateGrandFinal($stage, $wbMatches, $lbMatches, $wbRounds);

        // ── Grand Final Reset ──
        if ($stage->settings['grand_final_reset'] ?? false) {
            $this->generateGrandFinalReset($stage, $grandFinal);
        }

        // ── Third Place Match ──
        if ($stage->settings['third_place_match'] ?? false) {
            $this->generateThirdPlaceMatch($stage, $lbMatches, $wbRounds);
        }
    }

    /**
     * Generate the winners bracket (standard single-elimination).
     *
     * @param  array<int, CompetitionParticipant|null>  $seeded
     * @return array<int, array<int, CompetitionMatch>> Indexed by [round][sequence]
     */
    protected function generateWinnersBracket(
        CompetitionStage $stage,
        array $seeded,
        int $bracketSize,
        int $wbRounds,
    ): array {
        $wbMatches = [];
        $previousRoundMatches = [];

        for ($round = 1; $round <= $wbRounds; $round++) {
            $matchesInRound = (int) ($bracketSize / pow(2, $round));
            $currentRoundMatches = [];

            for ($seq = 1; $seq <= $matchesInRound; $seq++) {
                $match = CompetitionMatch::create([
                    'competition_id' => $stage->competition_id,
                    'competition_stage_id' => $stage->id,
                    'round_number' => $round,
                    'sequence' => $seq,
                    'status' => MatchStatus::Pending,
                    'settings' => ['bracket_side' => 'winners'],
                ]);

                $currentRoundMatches[$seq] = $match;

                if ($round === 1) {
                    $idx1 = ($seq - 1) * 2;
                    $idx2 = ($seq - 1) * 2 + 1;
                    $p1 = $seeded[$idx1] ?? null;
                    $p2 = $seeded[$idx2] ?? null;

                    if ($p1 !== null) {
                        MatchParticipant::create([
                            'match_id' => $match->id,
                            'competition_participant_id' => $p1->id,
                            'slot' => 1,
                        ]);
                    }

                    if ($p2 !== null) {
                        MatchParticipant::create([
                            'match_id' => $match->id,
                            'competition_participant_id' => $p2->id,
                            'slot' => 2,
                        ]);
                    }

                    if ($p1 !== null && $p2 === null) {
                        $this->applyBye($match, $p1);
                    } elseif ($p2 !== null && $p1 === null) {
                        $this->applyBye($match, $p2);
                    }
                }
            }

            // Wire WB round-to-round (winner → next WB round)
            if ($round > 1) {
                foreach ($currentRoundMatches as $seq => $targetMatch) {
                    $srcIdx1 = ($seq - 1) * 2 + 1;
                    $srcIdx2 = ($seq - 1) * 2 + 2;

                    if (isset($previousRoundMatches[$srcIdx1])) {
                        MatchConnection::create([
                            'source_match_id' => $previousRoundMatches[$srcIdx1]->id,
                            'source_outcome' => 'winner',
                            'target_match_id' => $targetMatch->id,
                            'target_slot' => 1,
                        ]);
                    }

                    if (isset($previousRoundMatches[$srcIdx2])) {
                        MatchConnection::create([
                            'source_match_id' => $previousRoundMatches[$srcIdx2]->id,
                            'source_outcome' => 'winner',
                            'target_match_id' => $targetMatch->id,
                            'target_slot' => 2,
                        ]);
                    }
                }
            }

            $wbMatches[$round] = $currentRoundMatches;
            $previousRoundMatches = $currentRoundMatches;
        }

        return $wbMatches;
    }

    /**
     * Generate the losers bracket.
     *
     * Losers bracket has (wbRounds - 1) * 2 rounds:
     * - Odd LB rounds receive losers dropping from the WB
     * - Even LB rounds are internal LB matches (no new entrants)
     *
     * LB round numbering is offset: stored as round 100+n to distinguish from WB.
     *
     * @param  array<int, array<int, CompetitionMatch>>  $wbMatches
     * @return array<int, array<int, CompetitionMatch>> Indexed by [lbRound][sequence]
     */
    protected function generateLosersBracket(
        CompetitionStage $stage,
        array $wbMatches,
        int $wbRounds,
    ): array {
        $lbTotalRounds = ($wbRounds - 1) * 2;
        $lbMatches = [];

        for ($lbRound = 1; $lbRound <= $lbTotalRounds; $lbRound++) {
            $currentRoundMatches = [];

            // Determine how many matches in this LB round
            $matchCount = $this->losersRoundMatchCount($wbRounds, $lbRound);

            for ($seq = 1; $seq <= $matchCount; $seq++) {
                $match = CompetitionMatch::create([
                    'competition_id' => $stage->competition_id,
                    'competition_stage_id' => $stage->id,
                    'round_number' => 100 + $lbRound,
                    'sequence' => $seq,
                    'status' => MatchStatus::Pending,
                    'settings' => ['bracket_side' => 'losers'],
                ]);

                $currentRoundMatches[$seq] = $match;
            }

            $lbMatches[$lbRound] = $currentRoundMatches;
        }

        // ── Wire connections ──
        $this->wireLosersDropdowns($wbMatches, $lbMatches, $wbRounds);
        $this->wireLosersInternal($lbMatches, $wbRounds);

        return $lbMatches;
    }

    /**
     * Wire WB losers dropping into LB entry rounds.
     *
     * WB round R losers → LB round (R-1)*2 - 1  (odd rounds)
     * For WB round 1:  losers → LB round 1
     * For WB round 2:  losers → LB round 3
     * For WB round 3:  losers → LB round 5
     * etc.
     *
     * @param  array<int, array<int, CompetitionMatch>>  $wbMatches
     * @param  array<int, array<int, CompetitionMatch>>  $lbMatches
     */
    protected function wireLosersDropdowns(array $wbMatches, array $lbMatches, int $wbRounds): void
    {
        for ($wbRound = 1; $wbRound <= $wbRounds; $wbRound++) {
            $lbTargetRound = $wbRound === 1 ? 1 : 2 * ($wbRound - 1);

            if (! isset($lbMatches[$lbTargetRound])) {
                continue;
            }

            $wbRoundMatches = array_values($wbMatches[$wbRound]);
            $lbRoundMatches = array_values($lbMatches[$lbTargetRound]);
            $lbMatchCount = count($lbRoundMatches);

            // For the first LB round, WB losers fill both slots.
            // For subsequent dropdown rounds, WB losers fill slot 2 (slot 1 comes from previous LB round).
            if ($wbRound === 1) {
                // WB R1 losers pair up in LB R1
                foreach ($lbRoundMatches as $i => $lbMatch) {
                    $wbIdx1 = $i * 2;
                    $wbIdx2 = $i * 2 + 1;

                    if (isset($wbRoundMatches[$wbIdx1])) {
                        MatchConnection::create([
                            'source_match_id' => $wbRoundMatches[$wbIdx1]->id,
                            'source_outcome' => 'loser',
                            'target_match_id' => $lbMatch->id,
                            'target_slot' => 1,
                        ]);
                    }

                    if (isset($wbRoundMatches[$wbIdx2])) {
                        MatchConnection::create([
                            'source_match_id' => $wbRoundMatches[$wbIdx2]->id,
                            'source_outcome' => 'loser',
                            'target_match_id' => $lbMatch->id,
                            'target_slot' => 2,
                        ]);
                    }
                }
            } else {
                // Later WB losers drop in to face the LB winners from the prior round
                foreach ($lbRoundMatches as $i => $lbMatch) {
                    if (isset($wbRoundMatches[$i])) {
                        MatchConnection::create([
                            'source_match_id' => $wbRoundMatches[$i]->id,
                            'source_outcome' => 'loser',
                            'target_match_id' => $lbMatch->id,
                            'target_slot' => 2,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Wire internal LB progression (winners of even rounds advance, odd rounds feed forward).
     *
     * @param  array<int, array<int, CompetitionMatch>>  $lbMatches
     */
    protected function wireLosersInternal(array $lbMatches, int $wbRounds): void
    {
        $lbTotalRounds = ($wbRounds - 1) * 2;

        for ($lbRound = 1; $lbRound < $lbTotalRounds; $lbRound++) {
            $nextRound = $lbRound + 1;

            if (! isset($lbMatches[$lbRound], $lbMatches[$nextRound])) {
                continue;
            }

            $currentMatches = array_values($lbMatches[$lbRound]);
            $nextMatches = array_values($lbMatches[$nextRound]);

            $isOddRound = $lbRound % 2 === 1;

            if ($isOddRound) {
                // Odd → Even (same match count): winner advances to slot 1
                foreach ($currentMatches as $i => $match) {
                    if (isset($nextMatches[$i])) {
                        MatchConnection::create([
                            'source_match_id' => $match->id,
                            'source_outcome' => 'winner',
                            'target_match_id' => $nextMatches[$i]->id,
                            'target_slot' => 1,
                        ]);
                    }
                }
            } else {
                // Even → Odd (halves match count): pairs combine into next round
                foreach ($nextMatches as $i => $nextMatch) {
                    $srcIdx1 = $i * 2;
                    $srcIdx2 = $i * 2 + 1;

                    if (isset($currentMatches[$srcIdx1])) {
                        MatchConnection::create([
                            'source_match_id' => $currentMatches[$srcIdx1]->id,
                            'source_outcome' => 'winner',
                            'target_match_id' => $nextMatch->id,
                            'target_slot' => 1,
                        ]);
                    }

                    if (isset($currentMatches[$srcIdx2])) {
                        MatchConnection::create([
                            'source_match_id' => $currentMatches[$srcIdx2]->id,
                            'source_outcome' => 'winner',
                            'target_match_id' => $nextMatch->id,
                            'target_slot' => 2,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Generate the grand final match and wire connections.
     *
     * @param  array<int, array<int, CompetitionMatch>>  $wbMatches
     * @param  array<int, array<int, CompetitionMatch>>  $lbMatches
     */
    protected function generateGrandFinal(
        CompetitionStage $stage,
        array $wbMatches,
        array $lbMatches,
        int $wbRounds,
    ): CompetitionMatch {
        $grandFinal = CompetitionMatch::create([
            'competition_id' => $stage->competition_id,
            'competition_stage_id' => $stage->id,
            'round_number' => 200,
            'sequence' => 1,
            'status' => MatchStatus::Pending,
            'settings' => ['bracket_side' => 'grand_final'],
        ]);

        // WB final winner → GF slot 1
        $wbFinal = $wbMatches[$wbRounds][1] ?? null;
        if ($wbFinal !== null) {
            MatchConnection::create([
                'source_match_id' => $wbFinal->id,
                'source_outcome' => 'winner',
                'target_match_id' => $grandFinal->id,
                'target_slot' => 1,
            ]);
        }

        // LB final winner → GF slot 2
        $lbTotalRounds = ($wbRounds - 1) * 2;
        $lbFinal = $lbMatches[$lbTotalRounds][1] ?? null;
        if ($lbFinal !== null) {
            MatchConnection::create([
                'source_match_id' => $lbFinal->id,
                'source_outcome' => 'winner',
                'target_match_id' => $grandFinal->id,
                'target_slot' => 2,
            ]);
        }

        return $grandFinal;
    }

    /**
     * Generate a grand final reset match.
     *
     * If the LB champion beats the WB champion in the grand final, a second
     * "reset" match is played because the WB champion has only lost once.
     * The match is pre-created but may be cancelled by the resolver if the
     * WB champion wins the original grand final.
     */
    protected function generateGrandFinalReset(
        CompetitionStage $stage,
        CompetitionMatch $grandFinal,
    ): void {
        $resetMatch = CompetitionMatch::create([
            'competition_id' => $stage->competition_id,
            'competition_stage_id' => $stage->id,
            'round_number' => 202,
            'sequence' => 1,
            'status' => MatchStatus::Pending,
            'settings' => ['bracket_side' => 'grand_final_reset'],
        ]);

        // GF winner → reset slot 1
        MatchConnection::create([
            'source_match_id' => $grandFinal->id,
            'source_outcome' => 'winner',
            'target_match_id' => $resetMatch->id,
            'target_slot' => 1,
        ]);

        // GF loser → reset slot 2
        MatchConnection::create([
            'source_match_id' => $grandFinal->id,
            'source_outcome' => 'loser',
            'target_match_id' => $resetMatch->id,
            'target_slot' => 2,
        ]);
    }

    /**
     * Generate a 3rd place match between the LB Final loser and the
     * penultimate LB round loser (determines 3rd vs 4th place).
     *
     * @param  array<int, array<int, CompetitionMatch>>  $lbMatches
     */
    protected function generateThirdPlaceMatch(
        CompetitionStage $stage,
        array $lbMatches,
        int $wbRounds,
    ): void {
        $lbTotalRounds = ($wbRounds - 1) * 2;

        if ($lbTotalRounds < 2) {
            return;
        }

        $lbFinal = $lbMatches[$lbTotalRounds][1] ?? null;
        $lbPenultimate = $lbMatches[$lbTotalRounds - 1][1] ?? null;

        if ($lbFinal === null || $lbPenultimate === null) {
            return;
        }

        $thirdPlaceMatch = CompetitionMatch::create([
            'competition_id' => $stage->competition_id,
            'competition_stage_id' => $stage->id,
            'round_number' => 201,
            'sequence' => 1,
            'status' => MatchStatus::Pending,
            'settings' => ['bracket_side' => 'third_place'],
        ]);

        MatchConnection::create([
            'source_match_id' => $lbFinal->id,
            'source_outcome' => 'loser',
            'target_match_id' => $thirdPlaceMatch->id,
            'target_slot' => 1,
        ]);

        MatchConnection::create([
            'source_match_id' => $lbPenultimate->id,
            'source_outcome' => 'loser',
            'target_match_id' => $thirdPlaceMatch->id,
            'target_slot' => 2,
        ]);
    }

    /**
     * Calculate the number of matches in a given LB round.
     *
     * Odd LB rounds (dropout entry rounds) have the same count as the following even round.
     * Even LB rounds halve the count for the next odd round.
     */
    protected function losersRoundMatchCount(int $wbRounds, int $lbRound): int
    {
        $bracketSize = (int) pow(2, $wbRounds);
        $wbR1Matches = $bracketSize / 2;

        // LB R1: half of WB R1 matches
        if ($lbRound === 1) {
            return (int) ($wbR1Matches / 2);
        }

        // Each pair of LB rounds (odd+even) halves the count
        // LB R1: wbR1/2, LB R2: wbR1/2
        // LB R3: wbR1/4, LB R4: wbR1/4
        // etc.
        $pair = (int) ceil($lbRound / 2); // 1,1,2,2,3,3...
        $count = (int) ($wbR1Matches / pow(2, $pair));

        return max(1, $count);
    }

    /**
     * @param  Collection<int, CompetitionParticipant>  $participants
     * @return array<int, CompetitionParticipant|null>
     */
    protected function seedParticipants(
        Collection $participants,
        int $bracketSize,
    ): array {
        $positions = $this->generateSeedOrder($bracketSize);
        $seeded = array_fill(0, $bracketSize, null);

        foreach ($participants->values() as $index => $participant) {
            $seeded[$positions[$index]] = $participant;
        }

        return $seeded;
    }

    /**
     * @return array<int, int>
     */
    protected function generateSeedOrder(int $bracketSize): array
    {
        $order = [0];

        while (count($order) < $bracketSize) {
            $newOrder = [];
            $size = count($order);

            foreach ($order as $pos) {
                $newOrder[] = $pos;
                $newOrder[] = 2 * $size - 1 - $pos;
            }

            $order = $newOrder;
        }

        $seedToPosition = [];
        foreach ($order as $rank => $position) {
            $seedToPosition[$rank] = $position;
        }

        return $seedToPosition;
    }

    protected function applyBye(CompetitionMatch $match, CompetitionParticipant $participant): void
    {
        $match->update([
            'status' => MatchStatus::Finished,
            'winner_participant_id' => $participant->id,
            'finished_at' => now(),
        ]);

        MatchParticipant::query()
            ->where('match_id', $match->id)
            ->where('competition_participant_id', $participant->id)
            ->update(['result' => 'bye']);
    }

    /**
     * Advance winners of WB R1 BYE matches through their winner connections.
     *
     * BYE matches are resolved during WB round 1 creation, before connections
     * exist. This runs after all connections are wired to place BYE winners
     * into their WB R2 matches.
     */
    protected function advanceByeWinners(CompetitionStage $stage): void
    {
        $byeMatches = CompetitionMatch::query()
            ->where('competition_stage_id', $stage->id)
            ->where('round_number', 1)
            ->where('status', MatchStatus::Finished)
            ->whereNotNull('winner_participant_id')
            ->get();

        foreach ($byeMatches as $match) {
            $connections = MatchConnection::query()
                ->where('source_match_id', $match->id)
                ->where('source_outcome', 'winner')
                ->get();

            foreach ($connections as $connection) {
                MatchParticipant::create([
                    'match_id' => $connection->target_match_id,
                    'competition_participant_id' => $match->winner_participant_id,
                    'slot' => $connection->target_slot,
                ]);
            }
        }
    }
}
