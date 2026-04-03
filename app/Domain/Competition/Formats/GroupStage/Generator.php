<?php

namespace App\Domain\Competition\Formats\GroupStage;

use App\Domain\Competition\Contracts\FormatGenerator;
use App\Domain\Competition\Formats\RoundRobin\Scheduler;
use App\Models\CompetitionStage;
use App\Models\CompetitionStageGroup;
use App\Models\CompetitionStageGroupMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Generator implements FormatGenerator
{
    public function __construct(
        protected Scheduler $scheduler,
    ) {}

    /**
     * Generate a group stage with round-robin matches within each group.
     *
     * Participants are divided into groups using serpentine seeding,
     * then each group plays a complete round-robin internally.
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
                'Group stage requires at least 4 participants.'
            );
        }

        $groupSize = $stage->settings['group_size'] ?? 4;
        $groupCount = $stage->settings['group_count']
            ?? (int) ceil($count / $groupSize);

        // Ensure at least 2 groups
        $groupCount = max($groupCount, 2);

        // Serpentine seeding: distribute participants across groups
        $groups = array_fill(0, $groupCount, []);
        $direction = 1; // 1 = left-to-right, -1 = right-to-left
        $groupIndex = 0;

        foreach ($participants as $participant) {
            $groups[$groupIndex][] = $participant;

            $nextIndex = $groupIndex + $direction;

            if ($nextIndex >= $groupCount || $nextIndex < 0) {
                $direction *= -1;
            } else {
                $groupIndex = $nextIndex;
            }
        }

        // Create group records and generate round-robin within each
        foreach ($groups as $index => $groupParticipants) {
            if (count($groupParticipants) < 2) {
                continue;
            }

            $groupName = 'Group '.chr(65 + $index); // A, B, C, ...
            $groupSlug = Str::slug($groupName);

            $group = CompetitionStageGroup::create([
                'competition_stage_id' => $stage->id,
                'name' => $groupName,
                'slug' => $groupSlug,
                'sequence' => $index + 1,
            ]);

            // Register group members
            foreach ($groupParticipants as $seed => $participant) {
                CompetitionStageGroupMember::create([
                    'competition_stage_group_id' => $group->id,
                    'competition_participant_id' => $participant->id,
                    'seed' => $seed + 1,
                ]);
            }

            // Generate round-robin matches for this group
            $this->scheduler->schedule(
                participants: new Collection($groupParticipants),
                competitionId: $stage->competition_id,
                stageId: $stage->id,
                matchSettings: ['group_id' => $group->id],
            );
        }
    }
}
