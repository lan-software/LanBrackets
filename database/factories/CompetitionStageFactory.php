<?php

namespace Database\Factories;

use App\Enums\StageStatus;
use App\Enums\StageType;
use App\Models\Competition;
use App\Models\CompetitionStage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CompetitionStage>
 */
class CompetitionStageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'competition_id' => Competition::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'order' => 0,
            'stage_type' => fake()->randomElement(StageType::cases()),
            'status' => StageStatus::Pending,
            'settings' => null,
            'progression_meta' => null,
        ];
    }

    public function singleElimination(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage_type' => StageType::SingleElimination,
        ]);
    }

    public function groupStage(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage_type' => StageType::GroupStage,
        ]);
    }
}
