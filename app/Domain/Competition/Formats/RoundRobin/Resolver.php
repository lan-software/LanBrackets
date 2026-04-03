<?php

namespace App\Domain\Competition\Formats\RoundRobin;

use App\Domain\Competition\Concerns\DetectsStageCompletion;
use App\Domain\Competition\Contracts\FormatResolver;
use App\Enums\MatchResult;
use App\Enums\MatchStatus;
use App\Models\CompetitionMatch;
use InvalidArgumentException;

class Resolver implements FormatResolver
{
    use DetectsStageCompletion;

    /**
     * Resolve a completed round-robin match.
     *
     * Unlike elimination formats, there is no bracket advancement.
     * Supports draws when scores are tied.
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

        $allowDraws = $match->stage->settings['allow_draws'] ?? true;

        if ($p1->score === $p2->score) {
            if (! $allowDraws) {
                throw new InvalidArgumentException(
                    "Match [{$match->id}] cannot be resolved: scores are tied and draws are not allowed."
                );
            }

            $p1->update(['result' => MatchResult::Draw]);
            $p2->update(['result' => MatchResult::Draw]);

            $match->update([
                'status' => MatchStatus::Finished,
                'finished_at' => now(),
            ]);

            $this->checkStageCompletion($match);

            return;
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

        $this->checkStageCompletion($match);
    }
}
