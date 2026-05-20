<?php

namespace App\Models;

use Database\Factories\ChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'creator_id',
    'original_challenge_id',
    'title',
    'description',
    'cover_path',
    'duration_days',
    'is_private',
    'has_palette',
    'palette_name',
    'palette_colors',
    'palette_source_url',
])]
class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'has_palette' => 'boolean',
            'palette_colors' => 'array',
            'duration_days' => 'integer',
        ];
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? '/storage/'.$this->cover_path : null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function originalChallenge(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_challenge_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(ChallengeDay::class)->orderBy('day_number');
    }

    public function userChallenges(): HasMany
    {
        return $this->hasMany(UserChallenge::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ChallengeLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ChallengeComment::class);
    }

    public function isLikedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->likes()->where('user_id', $user->id)->exists();
    }
}
