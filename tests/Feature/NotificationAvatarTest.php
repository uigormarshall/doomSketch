<?php

use App\Models\ChallengeDay;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserChallenge;
use App\Notifications\SubmissionLikedNotification;
use Livewire\Livewire;

function likeNotificationFrom(User $liker, User $recipient): void
{
    $uc = UserChallenge::factory()->create(['user_id' => $recipient->id]);
    $day = ChallengeDay::factory()->create(['challenge_id' => $uc->challenge_id]);
    $submission = Submission::factory()->create([
        'user_challenge_id' => $uc->id,
        'challenge_day_id' => $day->id,
    ]);

    $recipient->notify(new SubmissionLikedNotification($liker, $submission));
}

it('shows the notifying user avatar on the notifications page', function () {
    $rafaela = User::factory()->create(['name' => 'Rafaela', 'avatar_path' => 'avatars/rafaela/seeded.jpg']);
    $uigor = User::factory()->create(['name' => 'Uigor']);

    likeNotificationFrom($rafaela, $uigor);

    $this->actingAs($uigor);

    Livewire::test('pages::notifications')
        ->assertSee('Rafaela')
        ->assertSee('/storage/avatars/rafaela/seeded.jpg', false);
});

it('falls back to initials when the actor has no avatar', function () {
    $rafaela = User::factory()->create(['name' => 'Rafaela Leal', 'avatar_path' => null]);
    $uigor = User::factory()->create(['name' => 'Uigor']);

    likeNotificationFrom($rafaela, $uigor);

    $this->actingAs($uigor);

    Livewire::test('pages::notifications')
        ->assertSee('RL'); // initials
});

it('renders the actor avatar in the profile recent activity', function () {
    $rafaela = User::factory()->create(['name' => 'Rafaela', 'avatar_path' => 'avatars/rafaela/seeded.jpg']);
    $uigor = User::factory()->create(['name' => 'Uigor']);

    likeNotificationFrom($rafaela, $uigor);

    $this->actingAs($uigor);

    Livewire::test('pages::user-profile', ['user' => $uigor])
        ->assertSee('/storage/avatars/rafaela/seeded.jpg', false);
});

it('does not break when the notifying user was deleted', function () {
    $rafaela = User::factory()->create(['name' => 'Rafaela']);
    $uigor = User::factory()->create(['name' => 'Uigor']);

    likeNotificationFrom($rafaela, $uigor);
    $rafaela->delete();

    $this->actingAs($uigor);

    Livewire::test('pages::notifications')->assertOk();
});
