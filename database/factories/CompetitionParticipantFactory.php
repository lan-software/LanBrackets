<?php

namespace Database\Factories;

use App\Enums\ParticipantStatus;
use App\Models\Competition;
use App\Models\CompetitionParticipant;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitionParticipant>
 */
class CompetitionParticipantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'participant_type' => 'team',
            'participant_id' => Team::factory(),
            'seed' => null,
            'status' => ParticipantStatus::Registered,
            'checked_in_at' => null,
            'metadata' => null,
        ];
    }

    public function forTeam(?Team $team = null): static
    {
        return $this->state(fn (array $attributes) => [
            'participant_type' => 'team',
            'participant_id' => $team?->id ?? Team::factory(),
        ]);
    }

    public function forUser(?\App\Models\User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'participant_type' => 'user',
            'participant_id' => $user?->id ?? \App\Models\User::factory(),
        ]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ParticipantStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);
    }
}
