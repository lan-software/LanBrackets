<?php

namespace App\Domain\Competition\Formats\GroupStage;

use App\Domain\Competition\Contracts\StandingsCalculator as StandingsCalculatorContract;
use App\Domain\Competition\DTOs\StandingEntry;
use App\Enums\MatchStatus;
use App\Models\CompetitionMatch;
use App\Models\CompetitionStage;
use App\Models\CompetitionStageGroup;
use App\Models\CompetitionStageGroupMember;

class StandingsCalculator implements StandingsCalculatorContract
{
    /**
     * Calculate group stage standings.
     *
     * Returns participants ordered by: group winners first (1A, 1B, 1C, 1D),
     * then runners-up (2A, 2B, 2C, 2D), etc. — serpentine interleaving for
     * fair seeding into elimination brackets.
     *
     * @return array<int, StandingEntry>
     */
    public function calculate(CompetitionStage $stage): array
    {
        $settings = $stage->settings ?? [];
        $pointsWin = $settings['points_win'] ?? 3;
        $pointsDraw = $settings['points_draw'] ?? 1;
        $pointsLoss = $settings['points_loss'] ?? 0;

        $groups = CompetitionStageGroup::where('competition_stage_id', $stage->id)
            ->orderBy('sequence')
            ->get();

        /** @var array<int, array<int, StandingEntry>> $groupStandings */
        $groupStandings = [];

        foreach ($groups as $group) {
            $groupStandings[$group->id] = $this->calculateGroupStandings(
                $stage, $group, $pointsWin, $pointsDraw, $pointsLoss,
            );
        }

        // Interleave: all 1st-place finishers, then all 2nd-place, etc.
        return $this->interleaveStandings($groupStandings);
    }

    /**
     * Calculate standings within a single group.
     *
     * @return array<int, StandingEntry>
     */
    protected function calculateGroupStandings(
        CompetitionStage $stage,
        CompetitionStageGroup $group,
        int $pointsWin,
        int $pointsDraw,
        int $pointsLoss,
    ): array {
        $memberIds = CompetitionStageGroupMember::where('competition_stage_group_id', $group->id)
            ->pluck('competition_participant_id')
            ->all();

        $matches = CompetitionMatch::query()
            ->where('competition_stage_id', $stage->id)
            ->where('settings->group_id', $group->id)
            ->where('status', MatchStatus::Finished)
            ->with('matchParticipants')
            ->get();

        $stats = [];
        foreach ($memberIds as $id) {
            $stats[$id] = ['wins' => 0, 'losses' => 0, 'draws' => 0, 'points' => 0, 'score_diff' => 0];
        }

        foreach ($matches as $match) {
            $participants = $match->matchParticipants;
            if ($participants->count() < 2) {
                continue;
            }

            $p1 = $participants->firstWhere('slot', 1);
            $p2 = $participants->firstWhere('slot', 2);

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
                // Draw
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

        // Sort by points desc, then score_diff desc
        $sorted = $stats;
        uasort($sorted, function ($a, $b) {
            if ($a['points'] !== $b['points']) {
                return $b['points'] - $a['points'];
            }

            return $b['score_diff'] <=> $a['score_diff'];
        });

        $entries = [];
        $placement = 1;
        foreach ($sorted as $participantId => $s) {
            $entries[] = new StandingEntry(
                participantId: $participantId,
                placement: $placement++,
                wins: $s['wins'],
                losses: $s['losses'],
                draws: $s['draws'],
                points: $s['points'],
                tiebreaker: (float) $s['score_diff'],
                groupId: $group->id,
            );
        }

        return $entries;
    }

    /**
     * Interleave group standings for cross-group seeding.
     *
     * Result: all 1st-place finishers (group A, B, C...), then all 2nd-place, etc.
     * Within each placement tier, groups are ordered by sequence.
     *
     * @param  array<int, array<int, StandingEntry>>  $groupStandings
     * @return array<int, StandingEntry>
     */
    protected function interleaveStandings(array $groupStandings): array
    {
        $maxSize = 0;
        foreach ($groupStandings as $entries) {
            $maxSize = max($maxSize, count($entries));
        }

        $result = [];
        $overallPlacement = 1;

        for ($position = 0; $position < $maxSize; $position++) {
            foreach ($groupStandings as $entries) {
                if (isset($entries[$position])) {
                    $result[] = new StandingEntry(
                        participantId: $entries[$position]->participantId,
                        placement: $overallPlacement++,
                        wins: $entries[$position]->wins,
                        losses: $entries[$position]->losses,
                        draws: $entries[$position]->draws,
                        points: $entries[$position]->points,
                        tiebreaker: $entries[$position]->tiebreaker,
                        groupId: $entries[$position]->groupId,
                    );
                }
            }
        }

        return $result;
    }
}
