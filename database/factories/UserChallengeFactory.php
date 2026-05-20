<?php

namespace Database\Factories;

use App\Enums\UserChallengeStatus;
use App\Models\Challenge;
use App\Models\User;
use App\Models\UserChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserChallenge>
 */
class UserChallengeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'challenge_id' => Challenge::factory(),
            'start_date' => now()->toDateString(),
            'status' => UserChallengeStatus::Active,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => UserChallengeStatus::Completed]);
    }

    public function abandoned(): static
    {
        return $this->state(fn () => ['status' => UserChallengeStatus::Abandoned]);
    }
}
