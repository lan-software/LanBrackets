<?php

namespace App\Domain\Competition\Formats\RoundRobin;

use App\Domain\Competition\Contracts\StandingsCalculator as StandingsCalculatorContract;
use App\Domain\Competition\DTOs\StandingEntry;
use App\Enums\MatchStatus;
use App\Models\CompetitionMatch;
use App\Models\CompetitionStage;

class StandingsCalculator implements StandingsCalculatorContract
{
    /**
     * Calculate round robin standings using points system.
     *
     * @return array<int, StandingEntry>
     */
    public function calculate(CompetitionStage $stage): array
    {
        $settings = $stage->settings ?? [];
        $pointsWin = $settings['points_win'] ?? 3;
        $pointsDraw = $settings['points_draw'] ?? 1;
        $pointsLoss = $settings['points_loss'] ?? 0;

        $participants = $stage->competition
            ->participants()
            ->whereNull('metadata->disqualified')
            ->get();

        $matches = CompetitionMatch::query()
            ->where('competition_stage_id', $stage->id)
            ->where('status', MatchStatus::Finished)
            ->with('matchParticipants')
            ->get();

        $stats = [];
        foreach ($participants as $p) {
            $stats[$p->id] = ['wins' => 0, 'losses' => 0, 'draws' => 0, 'points' => 0, 'score_diff' => 0];
        }

        foreach ($matches as $match) {
            $mps = $match->matchParticipants;

            if ($mps->count() < 2) {
                continue;
            }

            $p1 = $mps->firstWhere('slot', 1);
            $p2 = $mps->firstWhere('slot', 2);

            if ($p1 === null || $p2 === null) {
                continue;
            }

            $id1 = $p1->competition_participant_id;
            $id2 = $p2->competition_participant_id;

            if (! isset($stats[$id1]) || ! isset($stats[$id2])) {
                continue;
            }

            $stats[$id1]['score_diff'] += ($p1->score ?? 0) - ($p2->score ?? 0);
            $stats[$id2]['score_diff'] += ($p2->score ?? 0) - ($p1->score ?? 0);

            if ($match->winner_participant_id === null) {
                $stats[$id1]['draws']++;
                $stats[$id1]['points'] += $pointsDraw;
                $stats[$id2]['draws']++;
                $stats[$id2]['points'] += $pointsDraw;
            } elseif ($match->winner_participant_id === $id1) {
                $stats[$id1]['wins']++;
                $stats[$id1]['points'] += $pointsWin;
                $stats[$id2]['losses']++;
                $stats[$id2]['points'] += $pointsLoss;
            } else {
                $stats[$id2]['wins']++;
                $stats[$id2]['points'] += $pointsWin;
                $stats[$id1]['losses']++;
                $stats[$id1]['points'] += $pointsLoss;
            }
        }

        uasort($stats, function ($a, $b) {
            if ($a['points'] !== $b['points']) {
                return $b['points'] - $a['points'];
            }

            return $b['score_diff'] <=> $a['score_diff'];
        });

        $entries = [];
        $placement = 1;
        foreach ($stats as $participantId => $s) {
            $entries[] = new StandingEntry(
                participantId: $participantId,
                placement: $placement++,
                wins: $s['wins'],
                losses: $s['losses'],
                draws: $s['draws'],
                points: $s['points'],
                tiebreaker: (float) $s['score_diff'],
            );
        }

        return $entries;
    }
}
