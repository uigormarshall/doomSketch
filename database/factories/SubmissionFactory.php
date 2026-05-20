<?php

namespace Database\Factories;

use App\Models\ChallengeDay;
use App\Models\Submission;
use App\Models\UserChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_challenge_id' => UserChallenge::factory(),
            'challenge_day_id' => ChallengeDay::factory(),
            'image_path' => 'submissions/'.fake()->uuid().'.png',
            'caption' => null,
        ];
    }
}
