<?php

namespace App\Services;

use App\Enums\UserChallengeStatus;
use App\Models\Challenge;
use App\Models\ChallengeDay;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserChallenge;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ChallengeService
{
    /**
     * Inicia uma instância 1:1 de um desafio para o usuário.
     */
    public function startChallenge(User $user, Challenge $challenge, ?CarbonInterface $startDate = null): UserChallenge
    {
        return UserChallenge::create([
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'start_date' => ($startDate ?? now())->toDateString(),
            'status' => UserChallengeStatus::Active,
        ]);
    }

    /**
     * Cria ou substitui a arte de um dia. Aceita envios retroativos sem restrição de streak.
     */
    public function submitArt(
        UserChallenge $userChallenge,
        ChallengeDay $day,
        UploadedFile $image,
        ?string $caption = null,
    ): Submission {
        if ($day->challenge_id !== $userChallenge->challenge_id) {
            throw new InvalidArgumentException('O dia informado não pertence ao desafio do usuário.');
        }

        return DB::transaction(function () use ($userChallenge, $day, $image, $caption) {
            $existing = Submission::where('user_challenge_id', $userChallenge->id)
                ->where('challenge_day_id', $day->id)
                ->first();

            $path = $image->store("submissions/{$userChallenge->user_id}", 'public');

            if ($existing) {
                Storage::disk('public')->delete($existing->image_path);

                $existing->update([
                    'image_path' => $path,
                    'caption' => $caption,
                ]);

                return $existing->fresh();
            }

            return Submission::create([
                'user_challenge_id' => $userChallenge->id,
                'challenge_day_id' => $day->id,
                'image_path' => $path,
                'caption' => $caption,
            ]);
        });
    }

    /**
     * Marca uma instância de desafio como concluída.
     */
    public function markCompleted(UserChallenge $userChallenge): void
    {
        $userChallenge->update(['status' => UserChallengeStatus::Completed]);
    }
}
