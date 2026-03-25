<?php

namespace Database\Factories;

use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Enums\CompetitionVisibility;
use App\Models\Competition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Competition>
 */
class CompetitionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->catchPhrase();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->paragraph(),
            'type' => fake()->randomElement(CompetitionType::cases()),
            'status' => CompetitionStatus::Draft,
            'visibility' => CompetitionVisibility::Private,
            'starts_at' => null,
            'ends_at' => null,
            'settings' => null,
            'published_at' => null,
        ];
    }

    public function tournament(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CompetitionType::Tournament,
        ]);
    }

    public function league(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CompetitionType::League,
        ]);
    }

    public function race(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CompetitionType::Race,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => CompetitionVisibility::Public,
            'published_at' => now(),
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CompetitionStatus::Running,
            'starts_at' => now()->subHour(),
        ]);
    }
}
