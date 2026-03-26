<?php

namespace App\Domain\Competition\Formats\DoubleElimination;

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
     * Resolve a completed double-elimination match.
     *
     * Advances the winner through 'winner' connections and the loser through
     * 'loser' connections (which drop into the losers bracket).
     * Handles grand final reset logic when enabled.
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
                "Match [{$match->id}] cannot be resolved: scores are tied."
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

        if ($this->isGrandFinalWithReset($match) && $winner->slot === 1) {
            $this->cancelResetMatch($match);

            return;
        }

        $this->advanceParticipant($match, $winner, 'winner');
        $this->advanceParticipant($match, $loser, 'loser');
    }

    /**
     * Check if this match is the grand final and the reset setting is enabled.
     */
    protected function isGrandFinalWithReset(CompetitionMatch $match): bool
    {
        if (($match->settings['bracket_side'] ?? '') !== 'grand_final') {
            return false;
        }

        $stage = $match->stage;

        return (bool) ($stage?->settings['grand_final_reset'] ?? false);
    }

    /**
     * Cancel the pre-generated reset match when the WB champion wins the grand final.
     */
    protected function cancelResetMatch(CompetitionMatch $grandFinal): void
    {
        $resetConnections = MatchConnection::query()
            ->where('source_match_id', $grandFinal->id)
            ->get();

        $resetMatchIds = $resetConnections->pluck('target_match_id')->unique();

        CompetitionMatch::whereIn('id', $resetMatchIds)
            ->where('status', MatchStatus::Pending)
            ->update(['status' => MatchStatus::Cancelled]);
    }

    /**
     * Place a participant into the next match(es) via MatchConnections.
     */
    protected function advanceParticipant(
        CompetitionMatch $match,
        MatchParticipant $participant,
        string $outcome,
    ): void {
        $connections = MatchConnection::query()
            ->where('source_match_id', $match->id)
            ->where('source_outcome', $outcome)
            ->get();

        foreach ($connections as $connection) {
            MatchParticipant::create([
                'match_id' => $connection->target_match_id,
                'competition_participant_id' => $participant->competition_participant_id,
                'slot' => $connection->target_slot,
            ]);
        }
    }
}
