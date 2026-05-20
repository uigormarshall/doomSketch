<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('seeds avatars only for users missing one', function () {
    Http::fake([
        'thispersondoesnotexist.com/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $without = User::factory()->create(['avatar_path' => null]);
    $withExisting = User::factory()->create(['avatar_path' => 'avatars/keep.png']);

    $this->artisan('users:seed-avatars', ['--sleep' => 0])->assertSuccessful();

    expect($without->fresh()->avatar_path)->toBe("avatars/{$without->id}/seeded.jpg")
        ->and($withExisting->fresh()->avatar_path)->toBe('avatars/keep.png');

    Storage::disk('public')->assertExists("avatars/{$without->id}/seeded.jpg");
});

it('replaces existing avatars when --force is passed', function () {
    Http::fake([
        'thispersondoesnotexist.com/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    Storage::disk('public')->put('avatars/old.png', 'old');
    $user = User::factory()->create(['avatar_path' => 'avatars/old.png']);

    $this->artisan('users:seed-avatars', ['--force' => true, '--sleep' => 0])->assertSuccessful();

    expect($user->fresh()->avatar_path)->toBe("avatars/{$user->id}/seeded.jpg");
    Storage::disk('public')->assertMissing('avatars/old.png');
});

it('skips users when the download fails and leaves them without an avatar', function () {
    Http::fake([
        'thispersondoesnotexist.com/*' => Http::response('nope', 503),
    ]);

    $user = User::factory()->create(['avatar_path' => null]);

    $this->artisan('users:seed-avatars', ['--sleep' => 0])->assertSuccessful();

    expect($user->fresh()->avatar_path)->toBeNull();
});
