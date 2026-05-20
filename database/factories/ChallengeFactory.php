<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'creator_id' => User::factory(),
            'original_challenge_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'duration_days' => fake()->randomElement([7, 14, 30]),
            'is_private' => false,
            'has_palette' => false,
            'palette_name' => null,
            'palette_colors' => null,
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => ['is_private' => true]);
    }

    public function withPalette(): static
    {
        return $this->state(fn () => [
            'has_palette' => true,
            'palette_name' => fake()->randomElement(['Game Boy', 'Cyberpunk 8', 'Pico-8', 'Dawnbringer 16']),
            'palette_colors' => fake()->randomElements(
                ['#0f380f', '#306230', '#8bac0f', '#9bbc0f', '#1a1c2c', '#5d275d', '#b13e53', '#ef7d57'],
                4,
            ),
        ]);
    }

    public function withCover(): static
    {
        return $this->state(fn () => ['cover_path' => 'covers/sample/cover.jpg']);
    }

    public function clonedFrom(Challenge $source): static
    {
        return $this->state(fn () => ['original_challenge_id' => $source->id]);
    }
}
