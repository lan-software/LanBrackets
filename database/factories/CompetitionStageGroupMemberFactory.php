<?php

namespace Database\Factories;

use App\Models\CompetitionParticipant;
use App\Models\CompetitionStageGroup;
use App\Models\CompetitionStageGroupMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitionStageGroupMember>
 */
class CompetitionStageGroupMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_stage_group_id' => CompetitionStageGroup::factory(),
            'competition_participant_id' => CompetitionParticipant::factory(),
            'seed' => null,
        ];
    }
}
