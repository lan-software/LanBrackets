<?php

namespace App\Domain\Competition\Formats\SingleElimination;

use App\Domain\Competition\Contracts\FormatResolver;
use App\Enums\MatchResult;
use App\Enums\MatchStatus;
use App\Models\CompetitionMatch;
use App\Models\MatchConnection;
use App\Models\MatchParticipant;
use InvalidArgumentException;

class Resolver implements FormatResolver
{
    /**
     * Resolve a completed single-elimination match.
     *
     * Determines the winner and loser from match participant scores,
     * then advances the winner through outgoing MatchConnections.
     */
    public function resolve(CompetitionMatch $match): void
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
                . 'Single elimination does not allow draws.'
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

        $this->advanceWinner($match, $winner);
    }

    /**
     * Place the match winner into the next match via MatchConnections.
     */
    protected function advanceWinner(CompetitionMatch $match, MatchParticipant $winner): void
    {
        $connections = MatchConnection::query()
            ->where('source_match_id', $match->id)
            ->where('source_outcome', 'winner')
            ->get();

        foreach ($connections as $connection) {
            MatchParticipant::create([
                'match_id' => $connection->target_match_id,
                'competition_participant_id' => $winner->competition_participant_id,
                'slot' => $connection->target_slot,
            ]);
        }
    }
}
