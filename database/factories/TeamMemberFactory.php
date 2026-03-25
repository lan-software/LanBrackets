<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMember>
 */
class TeamMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'role' => null,
            'is_captain' => false,
            'joined_at' => now(),
            'left_at' => null,
        ];
    }

    public function captain(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_captain' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'left_at' => now(),
        ]);
    }
}
