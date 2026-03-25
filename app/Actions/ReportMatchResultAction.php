<?php

namespace App\Actions;

use App\Domain\Competition\Services\FormatRegistry;
use App\Enums\MatchStatus;
use App\Models\CompetitionMatch;
use InvalidArgumentException;

class ReportMatchResultAction
{
    public function __construct(
        protected FormatRegistry $formatRegistry,
    ) {}

    /**
     * Report scores for a match and resolve it using the stage's format resolver.
     *
     * @param  array<int, int>  $scores  Map of slot number => score
     */
    public function execute(CompetitionMatch $match, array $scores): void
    {
        if ($match->status === MatchStatus::Finished) {
            throw new InvalidArgumentException(
                "Match [{$match->id}] is already finished."
            );
        }

        if ($match->status === MatchStatus::Cancelled) {
            throw new InvalidArgumentException(
                "Match [{$match->id}] is cancelled."
            );
        }

        $participants = $match->matchParticipants()->get();

        if ($participants->count() !== 2) {
            throw new InvalidArgumentException(
                "Match [{$match->id}] does not have exactly 2 participants (has {$participants->count()})."
            );
        }

        foreach ($scores as $slot => $score) {
            $mp = $participants->firstWhere('slot', $slot);

            if ($mp === null) {
                throw new InvalidArgumentException(
                    "No participant in slot [{$slot}] for match [{$match->id}]."
                );
            }

            $mp->update(['score' => $score]);
        }

        $stage = $match->stage;
        $resolver = $this->formatRegistry->resolver($stage->stage_type);

        $match->refresh();
        $resolver->resolve($match);
    }
}
