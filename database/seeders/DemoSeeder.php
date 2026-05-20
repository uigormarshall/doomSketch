<?php

namespace Database\Seeders;

use App\Enums\UserChallengeStatus;
use App\Models\Challenge;
use App\Models\ChallengeDay;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserChallenge;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating 50 demo users...');
        $users = $this->createUsers(50);

        $this->command->info('Creating 8 public challenges...');
        $challenges = $this->createChallenges($users, 8);

        $this->command->info('Generating 3–5 submissions per user (downloading images, this may take a minute)...');
        foreach ($users as $i => $user) {
            $count = $this->submissionsForUser($user, $challenges);
            $this->command->getOutput()->write("\r  user {$user->username} -> {$count} submissions ".str_repeat(' ', 20));
        }
        $this->command->getOutput()->writeln('');

        $this->command->info('Done.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function createUsers(int $n): \Illuminate\Support\Collection
    {
        $created = collect();

        for ($i = 0; $i < $n; $i++) {
            $name = fake()->name();
            $base = Str::slug(Str::before(fake()->unique()->safeEmail(), '@'), '');
            $base = $base !== '' ? $base : 'user';
            $username = $base;
            $suffix = 1;
            while (User::where('username', $username)->exists()) {
                $username = $base.$suffix++;
            }

            $created->push(User::factory()->create([
                'name' => $name,
                'username' => $username,
                'bio' => fake()->boolean(70) ? fake()->sentence(8) : null,
            ]));
        }

        return $created;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $users
     * @return \Illuminate\Support\Collection<int, Challenge>
     */
    protected function createChallenges(\Illuminate\Support\Collection $users, int $n): \Illuminate\Support\Collection
    {
        $promptPool = [
            'Sombra', 'Ciborgue', 'Ruínas', 'Tempestade', 'Espelho', 'Floresta', 'Solidão', 'Máquina',
            'Memória', 'Fogo', 'Oráculo', 'Espinhos', 'Despertar', 'Nebulosa', 'Anjo caído', 'Êxodo',
            'Pacto', 'Vidro', 'Vértice', 'Marés', 'Sussurro', 'Despedida', 'Eco', 'Pulso',
            'Crisálida', 'Mosaico', 'Tinta seca', 'Cinzas', 'Lobo', 'Templo',
        ];

        $palettes = [
            ['name' => 'Game Boy', 'colors' => ['#0f380f', '#306230', '#8bac0f', '#9bbc0f']],
            ['name' => 'Pico-8', 'colors' => ['#1a1c2c', '#5d275d', '#b13e53', '#ef7d57', '#ffcd75', '#a7f070']],
            ['name' => 'Dawnbringer 8', 'colors' => ['#000000', '#55415f', '#646964', '#d77355', '#508cd7', '#64b964', '#e6c86e', '#dcf5ff']],
            ['name' => 'Cyberpunk', 'colors' => ['#ff2a6d', '#05d9e8', '#005678', '#01012b']],
            ['name' => 'Vaporwave', 'colors' => ['#ff71ce', '#01cdfe', '#05ffa1', '#b967ff', '#fffb96']],
        ];

        $challenges = collect();

        for ($i = 0; $i < $n; $i++) {
            $duration = fake()->randomElement([7, 14, 30]);
            $hasPalette = fake()->boolean(60);
            $palette = $hasPalette ? fake()->randomElement($palettes) : null;

            $challenge = Challenge::create([
                'creator_id' => $users->random()->id,
                'original_challenge_id' => null,
                'title' => fake()->randomElement([
                    'Inktober Indie', 'Pixel Dailies', 'Doom Sketch Marathon', 'Shadow Studies',
                    'Cybertober', 'Lo-Fi Lab', 'Color Restricted', 'Daily Doodle',
                ]).' #'.($i + 1),
                'description' => fake()->paragraph(),
                'duration_days' => $duration,
                'is_private' => false,
                'has_palette' => $hasPalette,
                'palette_name' => $palette['name'] ?? null,
                'palette_colors' => $palette['colors'] ?? null,
            ]);

            $prompts = fake()->randomElements($promptPool, $duration, false);
            foreach ($prompts as $dayNumber => $prompt) {
                ChallengeDay::create([
                    'challenge_id' => $challenge->id,
                    'day_number' => $dayNumber + 1,
                    'prompt_text' => $prompt,
                ]);
            }

            $challenges->push($challenge);
        }

        return $challenges;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Challenge>  $challenges
     */
    protected function submissionsForUser(User $user, \Illuminate\Support\Collection $challenges): int
    {
        $challenge = $challenges->random()->load('days');
        $targetCount = min(random_int(3, 5), $challenge->days->count());
        $days = $challenge->days->random($targetCount);

        // Spread submissions across the last 365 days so the activity heatmap is populated.
        $dates = collect(range(1, $targetCount))
            ->map(fn () => now()->subDays(random_int(0, 364))->subMinutes(random_int(0, 1440)))
            ->sortBy(fn ($d) => $d->getTimestamp())
            ->values();

        $userChallenge = new UserChallenge([
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'start_date' => $dates->first()->copy()->subDays(random_int(0, 3))->toDateString(),
            'status' => UserChallengeStatus::Active,
        ]);
        $userChallenge->created_at = $dates->first();
        $userChallenge->updated_at = $dates->first();
        $userChallenge->save();

        $created = 0;
        foreach ($days as $i => $day) {
            $path = $this->downloadImage($user->id);
            if (! $path) {
                continue;
            }

            $when = $dates[$i];
            $submission = new Submission([
                'user_challenge_id' => $userChallenge->id,
                'challenge_day_id' => $day->id,
                'image_path' => $path,
                'caption' => fake()->boolean(80) ? fake()->sentence(6) : null,
            ]);
            $submission->created_at = $when;
            $submission->updated_at = $when;
            $submission->save();
            $created++;
        }

        return $created;
    }

    /**
     * Tag pool for Loremflickr (Flickr Creative Commons), focused on the
     * "art sketchbook" vibe of the project.
     */
    protected array $tagPool = [
        'sketch', 'sketchbook', 'drawing', 'illustration',
        'inktober', 'digitalart', 'conceptart',
    ];

    protected function downloadImage(int $userId): ?string
    {
        try {
            $tags = fake()->randomElements($this->tagPool, random_int(1, 2));
            $tagStr = implode(',', $tags);
            $lock = random_int(1, 99999);

            $response = Http::timeout(20)
                ->withOptions(['allow_redirects' => true])
                ->get("https://loremflickr.com/600/600/{$tagStr}", ['lock' => $lock]);

            if (! $response->ok() || strlen($response->body()) < 1000) {
                return null;
            }

            $filename = "submissions/{$userId}/".Str::random(40).'.jpg';
            Storage::disk('public')->put($filename, $response->body());

            return $filename;
        } catch (ConnectionException $e) {
            $this->command->warn("Image download failed: {$e->getMessage()}");

            return null;
        }
    }
}
