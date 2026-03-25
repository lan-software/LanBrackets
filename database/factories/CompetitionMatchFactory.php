<?php

namespace Database\Factories;

use App\Enums\MatchStatus;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitionMatch>
 */
class CompetitionMatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'competition_stage_id' => null,
            'round_number' => 1,
            'sequence' => 1,
            'status' => MatchStatus::Pending,
            'scheduled_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'winner_participant_id' => null,
            'loser_participant_id' => null,
            'settings' => null,
        ];
    }

    public function inStage(?CompetitionStage $stage = null): static
    {
        return $this->state(fn (array $attributes) => [
            'competition_stage_id' => $stage?->id ?? CompetitionStage::factory(),
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MatchStatus::Finished,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now(),
        ]);
    }
}
