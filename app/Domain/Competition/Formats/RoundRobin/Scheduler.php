<?php

namespace App\Domain\Competition\Formats\RoundRobin;

use App\Enums\MatchResult;
use App\Enums\MatchStatus;
use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\MatchParticipant;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shared round-robin scheduling service using the circle method.
 *
 * Used by both the RoundRobin generator and the GroupStage generator
 * to produce a complete round-robin schedule for a set of participants.
 */
class Scheduler
{
    /**
     * Generate a complete round-robin schedule.
     *
     * @param  Collection<int, CompetitionParticipant>  $participants
     * @param  array<string, mixed>  $matchSettings  Extra settings to merge into each match
     * @return Collection<int, CompetitionMatch>
     */
    public function schedule(
        Collection $participants,
        int $competitionId,
        int $stageId,
        array $matchSettings = [],
    ): Collection {
        $ids = $participants->values()->all();
        $count = count($ids);
        $hasBye = $count % 2 !== 0;

        if ($hasBye) {
            $ids[] = null; // virtual BYE participant
            $count++;
        }

        $totalRounds = $count - 1;
        $matchesPerRound = $count / 2;
        $createdMatches = new Collection;

        for ($round = 1; $round <= $totalRounds; $round++) {
            $sequence = 0;

            for ($i = 0; $i < $matchesPerRound; $i++) {
                $home = $ids[$i];
                $away = $ids[$count - 1 - $i];

                if ($home === null && $away === null) {
                    continue;
                }

                $sequence++;

                $match = CompetitionMatch::create([
                    'competition_id' => $competitionId,
                    'competition_stage_id' => $stageId,
                    'round_number' => $round,
                    'sequence' => $sequence,
                    'status' => MatchStatus::Pending,
                    'settings' => $matchSettings ?: null,
                ]);

                $realParticipants = [];

                if ($home !== null) {
                    MatchParticipant::create([
                        'match_id' => $match->id,
                        'competition_participant_id' => $home->id,
                        'slot' => 1,
                    ]);
                    $realParticipants[] = $home;
                }

                if ($away !== null) {
                    MatchParticipant::create([
                        'match_id' => $match->id,
                        'competition_participant_id' => $away->id,
                        'slot' => 2,
                    ]);
                    $realParticipants[] = $away;
                }

                // BYE: auto-advance when only one participant
                if (count($realParticipants) === 1) {
                    $this->applyBye($match, $realParticipants[0]);
                }

                $createdMatches->push($match);
            }

            // Circle method rotation: fix position 0, rotate the rest
            $fixed = $ids[0];
            $rotating = array_slice($ids, 1);
            array_unshift($rotating, array_pop($rotating));
            $ids = array_merge([$fixed], $rotating);
        }

        return $createdMatches;
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
            ->update(['result' => MatchResult::Bye]);
    }
}
