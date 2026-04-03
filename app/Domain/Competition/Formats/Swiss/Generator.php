<?php

namespace App\Domain\Competition\Formats\Swiss;

use App\Domain\Competition\Contracts\FormatGenerator;
use App\Enums\MatchResult;
use App\Enums\MatchStatus;
use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\CompetitionStage;
use App\Models\MatchParticipant;
use InvalidArgumentException;

class Generator implements FormatGenerator
{
    /**
     * Generate round 1 of a Swiss tournament.
     *
     * Subsequent rounds are generated dynamically by the Resolver
     * after all matches in the current round complete.
     */
    public function generate(CompetitionStage $stage): void
    {
        $participants = $stage->competition
            ->participants()
            ->whereNull('metadata->disqualified')
            ->orderBy('seed')
            ->get();

        $count = $participants->count();

        if ($count < 4) {
            throw new InvalidArgumentException(
                'Swiss format requires at least 4 participants.'
            );
        }

        $totalRounds = $stage->settings['total_rounds']
            ?? (int) ceil(log($count, 2));

        $stage->update([
            'settings' => array_merge($stage->settings ?? [], [
                'total_rounds' => $totalRounds,
            ]),
        ]);

        $this->generateRound($stage, $participants->values()->all(), 1);
    }

    /**
     * Generate matches for a single Swiss round.
     *
     * @param  array<int, CompetitionParticipant>  $paired  Participants in pairing order
     */
    public function generateRound(CompetitionStage $stage, array $paired, int $roundNumber): void
    {
        $sequence = 0;

        for ($i = 0; $i + 1 < count($paired); $i += 2) {
            $sequence++;

            $match = CompetitionMatch::create([
                'competition_id' => $stage->competition_id,
                'competition_stage_id' => $stage->id,
                'round_number' => $roundNumber,
                'sequence' => $sequence,
                'status' => MatchStatus::Pending,
            ]);

            MatchParticipant::create([
                'match_id' => $match->id,
                'competition_participant_id' => $paired[$i]->id,
                'slot' => 1,
            ]);

            MatchParticipant::create([
                'match_id' => $match->id,
                'competition_participant_id' => $paired[$i + 1]->id,
                'slot' => 2,
            ]);
        }

        // Odd participant: last one gets a BYE
        if (count($paired) % 2 !== 0) {
            $byeParticipant = end($paired);
            $sequence++;

            $match = CompetitionMatch::create([
                'competition_id' => $stage->competition_id,
                'competition_stage_id' => $stage->id,
                'round_number' => $roundNumber,
                'sequence' => $sequence,
                'status' => MatchStatus::Finished,
                'winner_participant_id' => $byeParticipant->id,
                'finished_at' => now(),
            ]);

            MatchParticipant::create([
                'match_id' => $match->id,
                'competition_participant_id' => $byeParticipant->id,
                'slot' => 1,
                'result' => MatchResult::Bye,
            ]);
        }
    }
}
