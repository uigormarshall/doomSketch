<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

#[Signature('users:seed-avatars {--force : Replace avatars users already have} {--sleep=1 : Seconds to wait between downloads}')]
#[Description('Fill user profile photos with AI faces from thispersondoesnotexist.com to humanize the beta.')]
class SeedUserAvatars extends Command
{
    private const SOURCE_URL = 'https://thispersondoesnotexist.com/';

    public function handle(): int
    {
        $query = User::query();

        if (! $this->option('force')) {
            $query->whereNull('avatar_path');
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('No users need an avatar. Use --force to replace existing ones.');

            return self::SUCCESS;
        }

        $sleep = max(0, (int) $this->option('sleep'));
        $failed = 0;

        $this->withProgressBar($users, function (User $user) use ($sleep, &$failed) {
            $image = $this->fetchFace();

            if ($image === null) {
                $failed++;

                return;
            }

            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $path = "avatars/{$user->id}/seeded.jpg";
            Storage::disk('public')->put($path, $image);
            $user->update(['avatar_path' => $path]);

            if ($sleep > 0) {
                sleep($sleep);
            }
        });

        $this->newLine(2);
        $this->info('Seeded '.($users->count() - $failed).' avatar(s).');

        if ($failed > 0) {
            $this->warn("{$failed} download(s) failed — re-run the command to retry them.");
        }

        return self::SUCCESS;
    }

    private function fetchFace(): ?string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; DoomsketchAvatarSeeder/1.0)',
        ])->timeout(20)->retry(2, 1000, throw: false)->get(self::SOURCE_URL);

        if (! $response->successful() || ! str_contains((string) $response->header('Content-Type'), 'image')) {
            return null;
        }

        return $response->body();
    }
}
