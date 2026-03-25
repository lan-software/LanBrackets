<?php

namespace Database\Factories;

use App\Models\CompetitionMatch;
use App\Models\MatchConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchConnection>
 */
class MatchConnectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_match_id' => CompetitionMatch::factory(),
            'source_outcome' => 'winner',
            'target_match_id' => CompetitionMatch::factory(),
            'target_slot' => 1,
        ];
    }
}
