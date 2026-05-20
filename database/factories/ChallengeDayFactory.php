<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\ChallengeDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChallengeDay>
 */
class ChallengeDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'challenge_id' => Challenge::factory(),
            'day_number' => 1,
            'prompt_text' => fake()->randomElement([
                'Sombra', 'Ciborgue', 'Ruínas', 'Tempestade', 'Espelho',
                'Floresta', 'Solidão', 'Máquina', 'Memória', 'Fogo',
            ]),
        ];
    }
}
