<?php

namespace Database\Factories;

use App\Models\CompetitionStage;
use App\Models\CompetitionStageGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CompetitionStageGroup>
 */
class CompetitionStageGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Group ' . fake()->unique()->randomLetter();

        return [
            'competition_stage_id' => CompetitionStage::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'sequence' => 0,
            'settings' => null,
        ];
    }
}
