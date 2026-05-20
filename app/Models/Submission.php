<?php

namespace App\Models;

use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_challenge_id', 'challenge_day_id', 'image_path', 'caption'])]
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    public function userChallenge(): BelongsTo
    {
        return $this->belongsTo(UserChallenge::class);
    }

    public function challengeDay(): BelongsTo
    {
        return $this->belongsTo(ChallengeDay::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(SubmissionLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SubmissionComment::class);
    }

    public function isLikedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->likes()->where('user_id', $user->id)->exists();
    }
}
