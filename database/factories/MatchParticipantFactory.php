<?php

namespace Database\Factories;

use App\Models\CompetitionMatch;
use App\Models\CompetitionParticipant;
use App\Models\MatchParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchParticipant>
 */
class MatchParticipantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => CompetitionMatch::factory(),
            'competition_participant_id' => CompetitionParticipant::factory(),
            'slot' => 1,
            'score' => null,
            'result' => null,
            'metadata' => null,
        ];
    }
}
