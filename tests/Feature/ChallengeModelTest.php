<?php

use App\Enums\UserChallengeStatus;
use App\Models\Challenge;
use App\Models\ChallengeDay;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserChallenge;

it('creates a challenge with creator relationship', function () {
    $challenge = Challenge::factory()->create();

    expect($challenge->creator)->toBeInstanceOf(User::class);
    expect($challenge->is_private)->toBeFalse();
    expect($challenge->has_palette)->toBeFalse();
});

it('casts palette_colors as array', function () {
    $challenge = Challenge::factory()->withPalette()->create();

    expect($challenge->palette_colors)->toBeArray()
        ->and($challenge->palette_colors)->toHaveCount(4);
});

it('orders challenge days by day_number', function () {
    $challenge = Challenge::factory()->create();
    ChallengeDay::factory()->create(['challenge_id' => $challenge->id, 'day_number' => 3]);
    ChallengeDay::factory()->create(['challenge_id' => $challenge->id, 'day_number' => 1]);
    ChallengeDay::factory()->create(['challenge_id' => $challenge->id, 'day_number' => 2]);

    expect($challenge->days->pluck('day_number')->all())->toBe([1, 2, 3]);
});

it('enforces unique day_number per challenge', function () {
    $challenge = Challenge::factory()->create();
    ChallengeDay::factory()->create(['challenge_id' => $challenge->id, 'day_number' => 1]);

    expect(fn () => ChallengeDay::factory()->create([
        'challenge_id' => $challenge->id,
        'day_number' => 1,
    ]))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

it('casts user challenge status as enum', function () {
    $uc = UserChallenge::factory()->create();

    expect($uc->status)->toBe(UserChallengeStatus::Active);
});

it('cascades day deletion when challenge is deleted', function () {
    $challenge = Challenge::factory()->create();
    ChallengeDay::factory()->create(['challenge_id' => $challenge->id]);

    $challenge->delete();

    expect(ChallengeDay::count())->toBe(0);
});

it('cascades submissions when user challenge is deleted', function () {
    $uc = UserChallenge::factory()->create();
    $day = ChallengeDay::factory()->create(['challenge_id' => $uc->challenge_id]);
    Submission::factory()->create([
        'user_challenge_id' => $uc->id,
        'challenge_day_id' => $day->id,
    ]);

    $uc->delete();

    expect(Submission::count())->toBe(0);
});

it('enforces unique submission per day per user_challenge', function () {
    $uc = UserChallenge::factory()->create();
    $day = ChallengeDay::factory()->create(['challenge_id' => $uc->challenge_id]);
    Submission::factory()->create([
        'user_challenge_id' => $uc->id,
        'challenge_day_id' => $day->id,
    ]);

    expect(fn () => Submission::factory()->create([
        'user_challenge_id' => $uc->id,
        'challenge_day_id' => $day->id,
    ]))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

it('tracks clone tree via original_challenge_id', function () {
    $original = Challenge::factory()->create();
    $clone = Challenge::factory()->clonedFrom($original)->create();

    expect($clone->originalChallenge->id)->toBe($original->id);
});
