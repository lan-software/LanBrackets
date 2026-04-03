<?php

namespace App\Domain\Competition\Formats\Swiss;

use App\Domain\Competition\Contracts\StandingsCalculator as StandingsCalculatorContract;
use App\Domain\Competition\DTOs\StandingEntry;
use App\Enums\MatchStatus;
use App\Models\CompetitionMatch;
use App\Models\CompetitionStage;

class StandingsCalculator implements StandingsCalculatorContract
{
    /**
     * Calculate Swiss standings using wins + Buchholz tiebreaker.
     *
     * @return array<int, StandingEntry>
     */
    public function calculate(CompetitionStage $stage): array
    {
        $participants = $stage->competition
            ->participants()
            ->whereNull('metadata->disqualified')
            ->get();

        $allMatches = CompetitionMatch::query()
            ->where('competition_stage_id', $stage->id)
            ->where('status', MatchStatus::Finished)
            ->with('matchParticipants')
            ->get();

        $wins = [];
        $losses = [];
        $opponents = [];

        foreach ($participants as $p) {
            $wins[$p->id] = 0;
            $losses[$p->id] = 0;
            $opponents[$p->id] = [];
        }

        foreach ($allMatches as $match) {
            $mps = $match->matchParticipants;

            if ($mps->count() < 2) {
                // BYE match
                if ($match->winner_participant_id) {
                    $wins[$match->winner_participant_id] = ($wins[$match->winner_participant_id] ?? 0) + 1;
                }

                continue;
            }

            if ($match->winner_participant_id) {
                $wins[$match->winner_participant_id] = ($wins[$match->winner_participant_id] ?? 0) + 1;
            }

            if ($match->loser_participant_id) {
                $losses[$match->loser_participant_id] = ($losses[$match->loser_participant_id] ?? 0) + 1;
            }

            $ids = $mps->pluck('competition_participant_id')->all();
            if (count($ids) === 2) {
                $opponents[$ids[0]][] = $ids[1];
                $opponents[$ids[1]][] = $ids[0];
            }
        }

        // Calculate Buchholz tiebreaker (sum of opponents' wins)
        $standings = [];
        foreach ($participants as $p) {
            $buchholz = 0.0;
            foreach ($opponents[$p->id] ?? [] as $oppId) {
                $buchholz += $wins[$oppId] ?? 0;
            }

            $standings[] = [
                'participant_id' => $p->id,
                'wins' => $wins[$p->id] ?? 0,
                'losses' => $losses[$p->id] ?? 0,
                'buchholz' => $buchholz,
            ];
        }

        usort($standings, function ($a, $b) {
            if ($a['wins'] !== $b['wins']) {
                return $b['wins'] - $a['wins'];
            }

            return $b['buchholz'] <=> $a['buchholz'];
        });

        $entries = [];
        foreach ($standings as $i => $s) {
            $entries[] = new StandingEntry(
                participantId: $s['participant_id'],
                placement: $i + 1,
                wins: $s['wins'],
                losses: $s['losses'],
                draws: 0,
                points: $s['wins'],
                tiebreaker: $s['buchholz'],
            );
        }

        return $entries;
    }
}
