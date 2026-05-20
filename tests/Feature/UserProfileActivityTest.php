<?php

use App\Models\Challenge;
use App\Models\ChallengeDay;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserChallenge;
use Livewire\Livewire;

function profileWithSubmission(): array
{
    $user = User::factory()->create();
    $challenge = Challenge::factory()->create(['duration_days' => 3]);
    $day = ChallengeDay::factory()->create([
        'challenge_id' => $challenge->id,
        'day_number' => 1,
        'prompt_text' => 'Neon city',
    ]);
    $uc = UserChallenge::factory()->create([
        'user_id' => $user->id,
        'challenge_id' => $challenge->id,
    ]);
    Submission::factory()->create([
        'user_challenge_id' => $uc->id,
        'challenge_day_id' => $day->id,
    ]);

    return [$user, $day];
}

it('renders the activity calendar with the submission counted', function () {
    [$user] = profileWithSubmission();

    $this->actingAs($user);

    $component = Livewire::test('pages::user-profile', ['user' => $user]);

    $calendar = $component->instance()->activityCalendar();

    expect($calendar['total'])->toBe(1)
        ->and($calendar['max'])->toBe(1)
        ->and($calendar['counts'][now()->format('Y-m-d')])->toBe(1);

    // today's cell is rendered with its count (locale-independent substring)
    $component->assertSee(now()->format('Y-m-d').' · 1', false);

    // and the active cell is actually painted with a green tone (level >= 1)
    $component->assertSee('bg-lime-900', false);
});

it('does not emit an Alpine-bound PHP expression in the gallery tab', function () {
    [$user, $day] = profileWithSubmission();

    $this->actingAs($user);

    Livewire::test('pages::user-profile', ['user' => $user])
        ->set('tab', 'gallery')
        // the plain <a> must use a server-rendered title, not an Alpine x-bind
        ->assertSee('title="'.$day->prompt_text.'"', false)
        ->assertDontSee(':title="$submission', false);
});
