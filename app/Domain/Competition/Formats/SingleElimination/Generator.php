<?php

namespace App\Domain\Competition\Formats\SingleElimination;

use App\Domain\Competition\Contracts\FormatGenerator;
use App\Enums\MatchStatus;
use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionStage;
use App\Models\MatchConnection;
use App\Models\MatchParticipant;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class Generator implements FormatGenerator
{
    /**
     * Generate a single-elimination bracket for the given stage.
     *
     * Creates matches for each round (including BYE placements for non-power-of-2
     * participant counts) and wires them together via MatchConnections so that
     * each match's winner feeds into the next round.
     */
    public function generate(CompetitionStage $stage): void
    {
        $participants = $stage->getStageParticipants();
        $count = $participants->count();

        if ($count < 2) {
            throw new InvalidArgumentException(
                'Single elimination requires at least 2 participants.'
            );
        }

        $totalRounds = (int) ceil(log($count, 2));
        $bracketSize = (int) pow(2, $totalRounds);
        $seeded = $this->seedParticipants($participants, $bracketSize);

        $previousRoundMatches = [];

        for ($round = 1; $round <= $totalRounds; $round++) {
            $matchesInRound = (int) ($bracketSize / pow(2, $round));
            $currentRoundMatches = [];

            for ($seq = 1; $seq <= $matchesInRound; $seq++) {
                $match = CompetitionMatch::create([
                    'competition_id' => $stage->competition_id,
                    'competition_stage_id' => $stage->id,
                    'round_number' => $round,
                    'sequence' => $seq,
                    'status' => MatchStatus::Pending,
                ]);

                $currentRoundMatches[] = $match;

                // First round: seed participants into match slots
                if ($round === 1) {
                    $slotIndex1 = ($seq - 1) * 2;
                    $slotIndex2 = ($seq - 1) * 2 + 1;

                    $p1 = $seeded[$slotIndex1] ?? null;
                    $p2 = $seeded[$slotIndex2] ?? null;

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

                    // BYE: auto-advance when only one participant in a match
                    if ($p1 !== null && $p2 === null) {
                        $this->applyBye($match, $p1);
                    } elseif ($p2 !== null && $p1 === null) {
                        $this->applyBye($match, $p2);
                    }
                }
            }

            // Wire previous round matches to current round via connections
            if ($round > 1) {
                foreach ($currentRoundMatches as $i => $targetMatch) {
                    $sourceIndex1 = $i * 2;
                    $sourceIndex2 = $i * 2 + 1;

                    if (isset($previousRoundMatches[$sourceIndex1])) {
                        MatchConnection::create([
                            'source_match_id' => $previousRoundMatches[$sourceIndex1]->id,
                            'source_outcome' => 'winner',
                            'target_match_id' => $targetMatch->id,
                            'target_slot' => 1,
                        ]);
                    }

                    if (isset($previousRoundMatches[$sourceIndex2])) {
                        MatchConnection::create([
                            'source_match_id' => $previousRoundMatches[$sourceIndex2]->id,
                            'source_outcome' => 'winner',
                            'target_match_id' => $targetMatch->id,
                            'target_slot' => 2,
                        ]);
                    }
                }
            }

            $previousRoundMatches = $currentRoundMatches;
        }

        // Advance BYE winners through connections now that all rounds are wired
        $this->advanceByeWinners($stage);

        if ($stage->settings['third_place_match'] ?? false) {
            $this->generateThirdPlaceMatch($stage, $totalRounds);
        }
    }

    /**
     * Generate a 3rd place match between the two semifinal losers.
     */
    protected function generateThirdPlaceMatch(CompetitionStage $stage, int $totalRounds): void
    {
        if ($totalRounds < 2) {
            return;
        }

        $semiFinals = CompetitionMatch::where('competition_stage_id', $stage->id)
            ->where('round_number', $totalRounds - 1)
            ->orderBy('sequence')
            ->get();

        if ($semiFinals->count() !== 2) {
            return;
        }

        $thirdPlaceMatch = CompetitionMatch::create([
            'competition_id' => $stage->competition_id,
            'competition_stage_id' => $stage->id,
            'round_number' => $totalRounds,
            'sequence' => 2,
            'status' => MatchStatus::Pending,
            'settings' => ['third_place' => true],
        ]);

        foreach ($semiFinals->values() as $i => $semi) {
            MatchConnection::create([
                'source_match_id' => $semi->id,
                'source_outcome' => 'loser',
                'target_match_id' => $thirdPlaceMatch->id,
                'target_slot' => $i + 1,
            ]);
        }
    }

    /**
     * Seed participants into bracket positions using standard tournament seeding.
     *
     * Higher seeds get BYE advantages (placed against nulls).
     * Classic bracket seeding: 1v(N), 2v(N-1), etc., reordered so that
     * top seeds are distributed evenly across the bracket halves.
     *
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
     * Generate standard bracket seed positions.
     *
     * Distributes seeds so that the highest seeds are maximally separated
     * in the bracket (e.g. 1 and 2 can only meet in the final).
     *
     * @return array<int, int> Map of seed rank → bracket position
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

        // Invert: map seed rank → position
        $seedToPosition = [];
        foreach ($order as $rank => $position) {
            $seedToPosition[$rank] = $position;
        }

        return $seedToPosition;
    }

    /**
     * Auto-advance a participant through a BYE match.
     */
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
     * Advance winners of BYE matches through their connections.
     *
     * BYE matches are resolved during round 1 creation, before connections
     * to round 2 exist. This method runs after all connections are wired
     * to place BYE winners into their next-round matches.
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
